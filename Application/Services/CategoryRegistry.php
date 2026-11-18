<?php

namespace App\Services\ApiKey\Application\Services;

use App\Services\ApiKey\Application\Contracts\CategoryStore;
use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;

/**
 * رجیستری دسته‌ها از دیتابیس (به‌ازای هر شعبه).
 *
 * منطق مجوز (نسخه‌ی جدید — اجتماعی، نه دقیق‌ترین prefix):
 *   ۱. قانون‌های جمع‌شده‌ی کلید (prefix → حداکثر سطح) ساخته می‌شوند.
 *   ۲. برای مسیر route از بلندترین prefix به کوتاه‌ترین می‌رویم.
 *   ۳. اولین قانون با سطح کافی = مجاز.
 *   ۴. write (۲) شامل read (۱) هم هست.
 *   ۵. مسیر در «هرگز» باشد → ۴۰۳، بدون توجه به دسته.
 *
 * دسته‌ی غیرفعال (is_active=false) شامل هیچ route‌ای نمی‌شود و به دسته‌ی کلی‌تر برنمی‌گردد.
 */
final class CategoryRegistry
{
    public function __construct(
        private readonly CategoryStore $store,
    ) {}

    // ─────────────────────── API پنل ───────────────────────

    /** @return array<int, array{...}> */
    public function listForBranch(int $branchId): array
    {
        return $this->store->listForBranch($branchId);
    }

    public function findForBranch(int $id, int $branchId): ?array
    {
        return $this->store->find($id, $branchId);
    }

    /**
     * اعتبارسنجی و ساخت دسته.
     *
     * @param  array<int, array{prefix:string, level:string}>  $rules
     */
    public function create(int $branchId, string $name, array $rules, int $operatorId): array
    {
        $this->validateRules($rules, $branchId);

        return $this->store->create($branchId, trim($name), $rules, $operatorId);
    }

    /**
     * ویرایش دسته.
     *
     * @param  array{name?:string, is_active?:bool, rules?:array<int,array{prefix:string,level:string}>}  $data
     */
    public function update(int $id, int $branchId, array $data): array
    {
        if (isset($data['rules'])) {
            $this->validateRules($data['rules'], $branchId, $id);
        }

        $result = $this->store->update($id, $branchId, $data);

        // کش همه‌ی کلیدهای وصل‌شده باید پاک شود (در ApiKeyService انجام می‌شود)
        return $result;
    }

    public function keyCount(int $id): int
    {
        return $this->store->keyCount($id);
    }

    public function hasKeys(int $id): bool
    {
        return $this->store->hasKeys($id);
    }

    // ─────────────────────── احراز هویت درخواست ───────────────────────

    /**
     * ساخت قانون‌های جمع‌شده برای یک کلید.
     * شناسه‌های دسته‌ها از DB خوانده می‌شوند؛ prefix → حداکثر سطح.
     *
     * @param  array<int, int>  $categoryIds  شناسه‌های دسته‌های کلید
     * @return array<string, int>  prefix → LEVEL_READ|LEVEL_WRITE
     */
    public function buildAggregatedRules(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $categories = $this->store->findMany($categoryIds);
        $rules = [];

        foreach ($categories as $cat) {
            if (! $cat['is_active']) {
                continue;
            }

            foreach ($cat['rules'] as $rule) {
                $prefix = $rule['prefix'];
                $level = ApiKeyRules::LEVELS[$rule['level']] ?? ApiKeyRules::LEVEL_READ;
                // اجتماع: بیشترین سطح برنده است
                $rules[$prefix] = max($rules[$prefix] ?? 0, $level);
            }
        }

        return $rules;
    }

    /**
     * مجوز یک درخواست کلید.
     * $aggregatedRules: prefix → سطح (از PreparedKey؛ قبلاً build شده و کش است).
     *
     * @param  array<string, int>  $aggregatedRules
     *
     * @throws ApiKeyException scopeDenied
     */
    public function authorize(array $aggregatedRules, string $uri, string $method, bool $isPublic): void
    {
        // مسیرهای هرگز
        if ($this->isNever($uri)) {
            throw ApiKeyException::scopeDenied();
        }

        $required = ApiKeyRules::levelForMethod($method);
        $uriSegments = ApiKeyRules::segments($uri);

        // قانون‌ها را از بلندترین prefix به کوتاه‌ترین مرتب می‌کنیم
        $sorted = $aggregatedRules;
        uksort($sorted, fn (string $a, string $b) => count(ApiKeyRules::segments($b)) <=> count(ApiKeyRules::segments($a)));

        foreach ($sorted as $prefix => $level) {
            if (! ApiKeyRules::hasPrefix($uriSegments, ApiKeyRules::segments($prefix))) {
                continue;
            }

            // write (۲) >= read (۱): write شامل read هم هست
            if (ApiKeyRules::levelCovers($required, $level)) {
                // کلید عمومی فقط read می‌تواند داشته باشد
                if ($isPublic && $required === ApiKeyRules::LEVEL_WRITE) {
                    throw ApiKeyException::scopeDenied(['category' => $prefix, 'level' => ApiKeyRules::levelKey($required)]);
                }

                return; // مجاز
            }
        }

        // هیچ قانونی نخورد یا سطح کافی نداشت
        throw ApiKeyException::scopeDenied();
    }

    /**
     * اعتبارسنجی categoryIds برای ساخت/ویرایش کلید.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     *
     * @throws ApiKeyException
     */
    public function validateCategoryIds(array $categoryIds, int $branchId, bool $isPublic): array
    {
        if ($categoryIds === []) {
            throw ApiKeyException::invalid('api_key.errors.grants_required', 'categories');
        }

        $categories = $this->store->findMany($categoryIds);

        foreach ($categoryIds as $id) {
            $cat = $categories[$id] ?? null;

            if ($cat === null || (int) $cat['branch_id'] !== $branchId) {
                throw ApiKeyException::invalid('api_key.errors.unknown_category', 'categories');
            }

            if (! $cat['is_active']) {
                throw ApiKeyException::invalid('api_key.errors.unknown_category', 'categories');
            }

            if ($isPublic) {
                foreach ($cat['rules'] as $rule) {
                    if ($rule['level'] === 'write') {
                        throw ApiKeyException::invalid('api_key.errors.level_not_public', 'categories');
                    }
                }
            }
        }

        return array_values(array_unique($categoryIds));
    }

    // ─────────────────────── کمکی ───────────────────────

    private function isNever(string $uri): bool
    {
        $segments = ApiKeyRules::segments($uri);

        foreach ((array) config('api_key.never', []) as $never) {
            if (ApiKeyRules::hasPrefix($segments, ApiKeyRules::segments((string) $never))) {
                return true;
            }
        }

        return false;
    }

    /**
     * اعتبارسنجی قانون‌ها برای ساخت/ویرایش دسته.
     *
     * @param  array<int, array{prefix:string, level:string}>  $rules
     */
    private function validateRules(array $rules, int $branchId, ?int $excludeCategoryId = null): void
    {
        if ($rules === []) {
            throw ApiKeyException::invalid('api_key.errors.category_rules_required', 'rules');
        }

        $never = (array) config('api_key.never', []);
        $seenPrefixes = [];

        foreach ($rules as $rule) {
            $prefix = trim((string) ($rule['prefix'] ?? ''), '/');
            $level = (string) ($rule['level'] ?? '');

            if ($prefix === '') {
                throw ApiKeyException::invalid('api_key.errors.invalid_prefix', 'rules');
            }

            if (! in_array($level, ['read', 'write'], true)) {
                throw ApiKeyException::invalid('api_key.errors.unknown_level', 'rules');
            }

            // prefix نباید در «هرگز» باشد
            $prefixSegments = ApiKeyRules::segments($prefix);

            foreach ($never as $n) {
                if (ApiKeyRules::hasPrefix($prefixSegments, ApiKeyRules::segments((string) $n))) {
                    throw ApiKeyException::invalid('api_key.errors.prefix_forbidden', 'rules');
                }
            }

            if (isset($seenPrefixes[$prefix])) {
                throw ApiKeyException::invalid('api_key.errors.duplicate_prefix', 'rules');
            }

            $seenPrefixes[$prefix] = true;
        }
    }
}
