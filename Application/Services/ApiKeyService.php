<?php

namespace App\Services\ApiKey\Application\Services;

use App\Services\ApiKey\Application\Contracts\ApiKeyStore;
use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * مدیریت کلیدها (ساخت، ویرایش، چرخش، باطل‌کردن) و بررسی کلید در هر درخواست.
 *
 * بررسی درخواست (authenticate) به این ترتیب: وجود کلید ← باطل نشده ← منقضی نشده ← IP/دامنه ← دسترسی.
 * سه حالت اول عمداً یک خطا می‌دهند (unauthenticated) تا علت افشا نشود. سقف درخواست در میدلور است.
 *
 * ورودی‌ها قبلاً با FormRequest شکل‌شان بررسی شده؛ اینجا قانون‌های کسب‌وکار بررسی می‌شوند
 * (دسترسی‌ها با config، یکتایی دامنه، سقف کلید شعبه، ...).
 */
final class ApiKeyService
{
    private const MAX_ORIGINS = 20;
    private const MAX_IPS = 50;

    public function __construct(
        private readonly ApiKeyStore $store,
        private readonly CategoryRegistry $categories,
    ) {}

    // ───────────────────────── پنل ─────────────────────────

    /** @return array<int, ApiKeyRecord> */
    public function list(int $branchId): array
    {
        return $this->store->listForBranch($branchId);
    }

    public function find(int $branchId, int $id): ApiKeyRecord
    {
        return $this->store->find($id, $branchId) ?? throw ApiKeyException::notFound();
    }

    /**
     * ساخت کلید؛ کلید خام فقط در plainKey رکورد برگشتی است.
     *
     * @param  array<string, mixed>  $data  name, description, type, environment, grants ({"دسته": ["read","write"]}), preset,
     *                                      allowed_ips, origins, rate_limit_per_minute, expires_at, no_expiry
     */
    public function issue(int $branchId, int $operatorId, array $data): ApiKeyRecord
    {
        $type = ApiKeyRules::TYPES[$data['type'] ?? 'secret'] ?? throw ApiKeyException::invalid('api_key.errors.invalid_type', 'type');
        $environment = ApiKeyRules::ENVIRONMENTS[$data['environment'] ?? 'live'] ?? throw ApiKeyException::invalid('api_key.errors.invalid_environment', 'environment');
        $public = $type === ApiKeyRules::TYPE_PUBLIC;

        $max = (int) config('api_key.max_keys_per_branch', 10);

        if ($max > 0 && $this->store->countActive($branchId) >= $max) {
            throw ApiKeyException::invalid('api_key.errors.max_keys', null, ['max' => $max]);
        }

        $grants = $this->categories->validateGrants((array) ($data['grants'] ?? []), $data['preset'] ?? null, $public);
        $origins = $this->origins((array) ($data['origins'] ?? []), $public, null);
        $prefix = ApiKeyRules::prefixFor($type, $environment, (array) config('api_key.prefixes', []));
        $plain = ApiKeyRules::generate($prefix, (int) config('api_key.key_length', 40));

        $record = $this->store->create([
            'branch_id' => $branchId,
            'name' => trim((string) $data['name']),
            'description' => $this->nullableText($data['description'] ?? null),
            'type' => $type,
            'environment' => $environment,
            'prefix' => ApiKeyRules::displayPrefix($plain, $prefix),
            'key_hash' => $this->store->hash($plain),
            'allowed_ips' => $this->ips((array) ($data['allowed_ips'] ?? [])) ?: null,
            'rate_limit_per_minute' => $this->rateLimit($data['rate_limit_per_minute'] ?? null),
            'expires_at' => $this->expiry($data, true),
            'created_by' => $operatorId,
        ], $grants, $origins);

        return $record->withPlainKey($plain);
    }

    /** ویرایش (نوع و محیط قابل تغییر نیستند). فقط فیلدهای آمده تغییر می‌کنند. */
    public function update(int $branchId, int $id, int $operatorId, array $data): ApiKeyRecord
    {
        $current = $this->requireNotRevoked($branchId, $id);
        $attributes = ['updated_by' => $operatorId];

        if (array_key_exists('name', $data)) {
            $attributes['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('description', $data)) {
            $attributes['description'] = $this->nullableText($data['description']);
        }

        if (array_key_exists('allowed_ips', $data)) {
            $attributes['allowed_ips'] = $this->ips((array) ($data['allowed_ips'] ?? [])) ?: null;
        }

        if (array_key_exists('rate_limit_per_minute', $data)) {
            $attributes['rate_limit_per_minute'] = $this->rateLimit($data['rate_limit_per_minute']);
        }

        // فقط با تاریخ جدید یا no_expiry=true تغییر می‌کند (no_expiry=false بدون تاریخ = بدون تغییر)
        if (! empty($data['expires_at']) || ! empty($data['no_expiry'])) {
            $attributes['expires_at'] = $this->expiry($data, false);
        }

        $grants = array_key_exists('grants', $data) || array_key_exists('preset', $data)
            ? $this->categories->validateGrants((array) ($data['grants'] ?? []), $data['preset'] ?? null, $current->isPublic())
            : null;

        $origins = array_key_exists('origins', $data)
            ? $this->origins((array) ($data['origins'] ?? []), $current->isPublic(), $current->id)
            : null;

        $record = $this->store->update($current->id, $attributes, $grants, $origins);
        $this->forget($current);

        return $record;
    }

    /**
     * کلید تازه روی همان ردیف (هویت ثابت). کلید قبلی تا $graceHours ساعت دیگر هم پذیرفته می‌شود (۰ = فوراً باطل).
     */
    public function rotate(int $branchId, int $id, int $operatorId, ?int $graceHours): ApiKeyRecord
    {
        $current = $this->requireNotRevoked($branchId, $id);

        if ($current->status() !== ApiKeyRules::STATUS_ACTIVE) {
            throw ApiKeyException::invalid('api_key.errors.not_active');
        }

        $maxGrace = (int) config('api_key.rotation.max_grace_hours', 168);
        $grace = max(0, min($graceHours ?? (int) config('api_key.rotation.grace_hours', 24), $maxGrace));
        $prefix = ApiKeyRules::prefixFor($current->type, $current->environment, (array) config('api_key.prefixes', []));
        $plain = ApiKeyRules::generate($prefix, (int) config('api_key.key_length', 40));

        $record = $this->store->update($current->id, [
            'prefix' => ApiKeyRules::displayPrefix($plain, $prefix),
            'key_hash' => $this->store->hash($plain),
            'previous_key_hash' => $grace > 0 ? $current->keyHash : null,
            'previous_key_expires_at' => $grace > 0 ? now()->addHours($grace) : null,
            'rotated_at' => now(),
            'updated_by' => $operatorId,
        ]);

        $this->forget($current);

        return $record->withPlainKey($plain);
    }

    /** باطل‌کردن فوری. ردیف برای سوابق می‌ماند؛ دامنه‌هایش آزاد می‌شوند. */
    public function revoke(int $branchId, int $id, int $operatorId): ApiKeyRecord
    {
        $current = $this->requireNotRevoked($branchId, $id);

        $record = $this->store->update($current->id, [
            'revoked_at' => now(),
            'revoked_by' => $operatorId,
            'previous_key_hash' => null,
            'previous_key_expires_at' => null,
        ], null, []);

        $this->forget($current);

        return $record;
    }

    // ───────────────────────── بررسی درخواست ─────────────────────────

    /**
     * هویت کلید: وجود، باطل/منقضی نبودن، IP و Origin مجاز. «دسترسی به route» جدا و با
     * CategoryRegistry::authorize بررسی می‌شود (ApiKeyAuth هر دو را به این ترتیب صدا می‌زند).
     *
     * @throws ApiKeyException unauthenticated | sourceDenied
     */
    public function authenticate(string $plainKey, ?string $ip, ?string $origin): ApiKeyRecord
    {
        $hash = $this->store->hash($plainKey);
        $ttl = max(0, (int) config('api_key.cache_seconds', 60));
        $record = $ttl > 0
            ? Cache::remember($this->cacheKey($hash), $ttl, fn () => $this->store->findByHash($hash))
            : $this->store->findByHash($hash);

        if (! $record instanceof ApiKeyRecord) {
            throw ApiKeyException::unauthenticated();
        }

        // کلید قبلی (بعد از چرخش) فقط تا پایان مهلت
        $now = now();
        $viaPrevious = ! hash_equals($record->keyHash, $hash);

        if ($viaPrevious && ($record->previousKeyHash === null || ! hash_equals($record->previousKeyHash, $hash)
            || $record->previousKeyExpiresAt === null || $record->previousKeyExpiresAt <= $now)) {
            throw ApiKeyException::unauthenticated();
        }

        if ($record->status($now) !== ApiKeyRules::STATUS_ACTIVE) {
            throw ApiKeyException::unauthenticated();
        }

        if (! ApiKeyRules::ipAllowed($ip, $record->allowedIps)) {
            throw ApiKeyException::sourceDenied();
        }

        // کلید عمومی فقط از دامنه‌های ثبت‌شده (Origin مرورگر)
        if ($record->isPublic()) {
            $normalized = $origin !== null ? ApiKeyRules::normalizeOrigin($origin) : null;

            if ($normalized === null || ! in_array($normalized, $record->origins, true)) {
                throw ApiKeyException::sourceDenied();
            }
        }

        return $record;
    }

    /** ثبت آخرین استفاده؛ حداکثر هر touch_every_seconds یک‌بار برای هر کلید. شکستش درخواست را متوقف نمی‌کند. */
    public function touch(ApiKeyRecord $record, ?string $ip): void
    {
        try {
            $every = max(1, (int) config('api_key.touch_every_seconds', 300));

            if (Cache::add('api_key:touch:' . $record->id, 1, $every)) {
                $this->store->touch($record->id, $ip, now());
            }
        } catch (\Throwable $e) {
            Log::warning('ApiKey: updating last use failed', ['api_key_id' => $record->id, 'error' => $e->getMessage()]);
        }
    }

    // ───────────────────────── کمکی ─────────────────────────

    private function requireNotRevoked(int $branchId, int $id): ApiKeyRecord
    {
        $record = $this->find($branchId, $id);

        if ($record->revokedAt !== null) {
            throw ApiKeyException::invalid('api_key.errors.revoked');
        }

        return $record;
    }

    private function forget(ApiKeyRecord $record): void
    {
        Cache::forget($this->cacheKey($record->keyHash));

        if ($record->previousKeyHash !== null) {
            Cache::forget($this->cacheKey($record->previousKeyHash));
        }
    }

    private function cacheKey(string $hash): string
    {
        return 'api_key:h:' . $hash;
    }

    /** @return array<int, string> */
    private function origins(array $values, bool $public, ?int $exceptKeyId): array
    {
        $values = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== ''));

        if (! $public) {
            if ($values !== []) {
                throw ApiKeyException::invalid('api_key.errors.origins_only_public', 'origins');
            }

            return [];
        }

        if ($values === []) {
            throw ApiKeyException::invalid('api_key.errors.origins_required', 'origins');
        }

        if (count($values) > self::MAX_ORIGINS) {
            throw ApiKeyException::invalid('api_key.errors.too_many_origins', 'origins', ['max' => self::MAX_ORIGINS]);
        }

        $clean = [];

        foreach ($values as $value) {
            $origin = ApiKeyRules::normalizeOrigin($value);

            if ($origin === null) {
                throw ApiKeyException::invalid('api_key.errors.invalid_origin', 'origins', ['value' => mb_substr($value, 0, 100)]);
            }

            $clean[$origin] = $origin;
        }

        $clean = array_values($clean);
        $taken = $this->store->takenOrigins($clean, $exceptKeyId);

        if ($taken !== []) {
            throw ApiKeyException::invalid('api_key.errors.origin_taken', 'origins', ['value' => implode('، ', $taken)]);
        }

        return $clean;
    }

    /** @return array<int, string> */
    private function ips(array $values): array
    {
        $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== '')));

        if (count($values) > self::MAX_IPS) {
            throw ApiKeyException::invalid('api_key.errors.too_many_ips', 'allowed_ips', ['max' => self::MAX_IPS]);
        }

        foreach ($values as $value) {
            if (! ApiKeyRules::validIpRule($value)) {
                throw ApiKeyException::invalid('api_key.errors.invalid_ip', 'allowed_ips', ['value' => mb_substr($value, 0, 60)]);
            }
        }

        return $values;
    }

    private function rateLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $max = (int) config('api_key.rate_limit.max', 6000);
        $value = (int) $value;

        if ($value < 1 || $value > $max) {
            throw ApiKeyException::invalid('api_key.errors.invalid_rate_limit', 'rate_limit_per_minute', ['max' => $max]);
        }

        return $value;
    }

    /** انقضا: no_expiry ← null؛ تاریخ داده‌شده (باید آینده باشد)؛ در ساخت بدون هر دو ← پیش‌فرض config */
    private function expiry(array $data, bool $creating): ?Carbon
    {
        if (! empty($data['no_expiry'])) {
            return null;
        }

        if (! empty($data['expires_at'])) {
            $date = Carbon::parse((string) $data['expires_at']);

            if ($date <= now()) {
                throw ApiKeyException::invalid('api_key.errors.expiry_past', 'expires_at');
            }

            return $date;
        }

        $days = (int) config('api_key.default_expiry_days', 180);

        return $creating && $days > 0 ? now()->addDays($days) : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
