<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\ApiKeyStore;
use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Infrastructure\Models\ApiKey;
use App\Services\ApiKey\Infrastructure\Models\ApiKeyCategoryKey;
use App\Services\ApiKey\Infrastructure\Models\ApiKeyOrigin;
use App\Services\ApiKey\Domain\ApiKeyException;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/** ذخیره‌ی کلیدها با Eloquent (بدون DB:: خام) + hash کلید با HMAC-SHA256. */
final class EloquentApiKeyStore implements ApiKeyStore
{
    private const RELATIONS = ['categoryKeys', 'origins'];

    public function hash(string $plainKey): string
    {
        $secret = (string) (config('api_key.hash_secret') ?: config('app.key'));

        // fail-closed: بدون کلید مخفی hash قابل حدس می‌شد
        if ($secret === '') {
            throw new RuntimeException('ApiKey: hash secret is not configured (api_key.hash_secret or app.key).');
        }

        return hash_hmac('sha256', $plainKey, $secret);
    }

    public function findByHash(string $hash): ?ApiKeyRecord
    {
        $key = ApiKey::query()->with(self::RELATIONS)
            ->where('key_hash', $hash)
            ->orWhere(fn ($q) => $q->where('previous_key_hash', $hash)->where('previous_key_expires_at', '>', now()))
            ->first();

        return $key?->toRecord();
    }

    public function find(int $id, int $branchId): ?ApiKeyRecord
    {
        return ApiKey::query()->with(self::RELATIONS)
            ->where('branch_id', $branchId)
            ->find($id)
            ?->toRecord();
    }

    public function listForBranch(int $branchId): array
    {
        return ApiKey::query()->with(self::RELATIONS)
            ->where('branch_id', $branchId)
            ->orderByRaw('revoked_at IS NULL DESC')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiKey $key) => $key->toRecord())
            ->all();
    }

    public function countActive(int $branchId): int
    {
        return ApiKey::query()->where('branch_id', $branchId)->whereNull('revoked_at')->count();
    }

    public function takenOrigins(array $origins, ?int $exceptKeyId = null): array
    {
        if ($origins === []) {
            return [];
        }

        return ApiKeyOrigin::query()
            ->whereIn('origin', $origins)
            ->when($exceptKeyId !== null, fn ($q) => $q->where('api_key_id', '!=', $exceptKeyId))
            ->pluck('origin')
            ->all();
    }

    public function create(array $attributes, array $categoryIds, array $origins): ApiKeyRecord
    {
        return $this->transaction(function () use ($attributes, $categoryIds, $origins) {
            $key = ApiKey::query()->create($attributes);
            $this->syncCategoryKeys($key->id, $categoryIds);
            $this->syncOrigins($key->id, $origins);

            return $key->load(self::RELATIONS)->toRecord();
        });
    }

    public function update(int $id, array $attributes, ?array $categoryIds = null, ?array $origins = null): ApiKeyRecord
    {
        return $this->transaction(function () use ($id, $attributes, $categoryIds, $origins) {
            $key = ApiKey::query()->lockForUpdate()->findOrFail($id);
            $key->fill($attributes)->save();

            if ($categoryIds !== null) {
                $this->syncCategoryKeys($key->id, $categoryIds);
            }

            if ($origins !== null) {
                $this->syncOrigins($key->id, $origins);
            }

            return $key->load(self::RELATIONS)->toRecord();
        });
    }

    public function touch(int $id, ?string $ip, DateTimeInterface $at): void
    {
        ApiKey::query()->whereKey($id)->toBase()->update([
            'last_used_at' => $at,
            'last_used_ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
        ]);
    }

    /** @param  array<int, int>  $categoryIds  شناسه‌های دسته‌ها */
    private function syncCategoryKeys(int $keyId, array $categoryIds): void
    {
        ApiKeyCategoryKey::query()->where('api_key_id', $keyId)->delete();

        $rows = [];

        foreach (array_unique($categoryIds) as $categoryId) {
            $rows[] = ['api_key_id' => $keyId, 'category_id' => (int) $categoryId];
        }

        if ($rows !== []) {
            ApiKeyCategoryKey::query()->insert($rows);
        }
    }

    /** @param  array<int, string>  $origins */
    private function syncOrigins(int $keyId, array $origins): void
    {
        ApiKeyOrigin::query()->where('api_key_id', $keyId)->delete();

        $now = now();

        if ($origins !== []) {
            ApiKeyOrigin::query()->insert(array_map(fn (string $o) => ['api_key_id' => $keyId, 'origin' => $o, 'created_at' => $now], $origins));
        }
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function transaction(\Closure $callback): mixed
    {
        try {
            return (new ApiKey)->getConnection()->transaction($callback);
        } catch (UniqueConstraintViolationException) {
            // مسابقه‌ی دو درخواست هم‌زمان روی یک دامنه (بررسی takenOrigins قبلاً رد شده بود)
            throw ApiKeyException::invalid('api_key.errors.origin_taken', 'origins', ['value' => '']);
        }
    }
}
