<?php

namespace App\Services\ApiKey\Application\Services;

use App\Services\ApiKey\Application\Contracts\CategoryStore;
use App\Services\ApiKey\Application\Contracts\RouteCatalog;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;

/**
 * رجیستری دسته‌ها از دیتابیس (به‌ازای هر شعبه) + منطق مجوز اجتماعی.
 *
 * مجوز یک درخواست:
 *   ۱. مسیر route یا مسیر واقعی درخواست در «هرگز» باشد ← ۴۰۳ (بدون توجه به دسته)
 *   ۲. قانون‌های جمع‌شده‌ی کلید (prefix → حداکثر سطح) از بلندترین prefix به کوتاه‌ترین
 *   ۳. اولین قانونی که سطح کافی داشته باشد مجاز می‌کند (write شامل read هم هست)
 *   ۴. هیچ قانونی نخورد ← ۴۰۳ همراه prefix و سطح لازم (فقط برای کلید معتبر)
 * دسته‌ی غیرفعال شامل هیچ route ای نمی‌شود.
 */
final class CategoryRegistry
{
    public function __construct(
        private readonly CategoryStore $store,
        private readonly RouteCatalog $routes,
    ) {}

    // ─────────────────────── API پنل ───────────────────────

    public function listForBranch(int $branchId): array
    {
        return $this->store->listForBranch($branchId);
    }

    public function findForBranch(int $id, int $branchId): ?array
    {
        return $this->store->find($id, $branchId);
    }

    /** درخت prefix ها (با شمارنده‌ی read/write هر گره) برای انتخاب در پنل */
    public function tree(): array
    {
        return $this->routes->tree();
    }

    /** @param  array<int, array{prefix:string, level:string}>  $rules */
    public function create(int $branchId, string $name, array $rules, int $operatorId): array
    {
        $max = (int) config('api_key.max_categories_per_branch', 50);

        if ($max > 0 && count($this->store->listForBranch($branchId)) >= $max) {
            throw ApiKeyException::invalid('api_key.errors.too_many_categories', null, ['max' => $max]);
        }

        return $this->store->create($branchId, trim($name), $this->validateRules($rules), $operatorId);
    }

    /** @param  array{name?:string, is_active?:bool, rules?:array<int,array{prefix:string,level:string}>}  $data */
    public function update(int $id, int $branchId, array $data): array
    {
        if ($this->store->find($id, $branchId) === null) {
            throw ApiKeyException::notFound();
        }

        if (array_key_exists('rules', $data)) {
            $data['rules'] = $this->validateRules((array) $data['rules']);
        }

        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        // پاک‌کردن کش کلیدهای وصل‌شده: ApiKeyService::forgetCategory (توسط کنترلر صدا زده می‌شود)
        return $this->store->update($id, $branchId, $data);
    }

    /** حذف فقط وقتی هیچ کلیدی به دسته وصل نیست */
    public function delete(int $id, int $branchId): void
    {
        if ($this->store->find($id, $branchId) === null) {
            throw ApiKeyException::notFound();
        }

        if ($this->store->hasKeys($id)) {
            throw ApiKeyException::invalid('api_key.errors.category_has_keys');
        }

        $this->store->delete($id, $branchId);
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
     * قانون‌های جمع‌شده‌ی یک کلید: prefix → حداکثر سطح (اجتماع دسته‌های فعال).
     *
     * @param  array<int, int>  $categoryIds
     * @return array<string, int>
     */
    public function buildAggregatedRules(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rules = [];

        foreach ($this->store->findMany($categoryIds) as $cat) {
            if (! $cat['is_active']) {
                continue;
            }

            foreach ($cat['rules'] as $rule) {
                $level = ApiKeyRules::LEVELS[$rule['level']] ?? ApiKeyRules::LEVEL_READ;
                $rules[$rule['prefix']] = max($rules[$rule['prefix']] ?? 0, $level);
            }
        }

        return $rules;
    }

    /**
     * «دسترسی‌های موثر» برای نمایش به مدیر: اجتماع دسته‌ها.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array{prefix:string, level:string}>
     */
    public function effectiveAccess(array $categoryIds): array
    {
        $rules = $this->buildAggregatedRules($categoryIds);
        ksort($rules);

        return array_map(
            fn(string $prefix, int $level) => ['prefix' => $prefix, 'level' => ApiKeyRules::levelKey($level)],
            array_keys($rules),
            $rules,
        );
    }

    /**
     * @param  array<string, int>  $aggregatedRules
     * @param  string  $uri  الگوی route (api/v2/group/{group})
     * @param  string  $path  مسیر واقعی درخواست (برای «هرگز»: route با پارامتر در بخش اول نباید دور بزندش)
     * @param  int  $required  سطح لازم (RouteLevel::for)
     *
     * @throws ApiKeyException scopeDenied
     */
    public function authorize(array $aggregatedRules, string $uri, string $path, int $required, bool $isPublic): void
    {
        if ($this->isNever($uri) || $this->isNever($path)) {
            throw ApiKeyException::scopeDenied();
        }

        $uriSegments = ApiKeyRules::segments($uri);

        $sorted = $aggregatedRules;
        uksort($sorted, fn(string $a, string $b) => count(ApiKeyRules::segments($b)) <=> count(ApiKeyRules::segments($a)));

        foreach ($sorted as $prefix => $level) {
            if (! ApiKeyRules::hasPrefix($uriSegments, ApiKeyRules::segments($prefix))) {
                continue;
            }

            if (! ApiKeyRules::levelCovers($required, $level)) {
                continue; // شاید prefix کوتاه‌تری سطح کافی داشته باشد
            }

            // کلید عمومی هرگز write نمی‌گیرد (حتی اگر دسته بعد از اتصال ویرایش شده باشد)
            if ($isPublic && $required === ApiKeyRules::LEVEL_WRITE) {
                break;
            }

            return;
        }

        throw ApiKeyException::scopeDenied([
            'prefix' => $this->suggestPrefix($uriSegments),
            'level' => ApiKeyRules::levelKey($required),
        ]);
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     *
     * @throws ApiKeyException
     */
    public function validateCategoryIds(array $categoryIds, int $branchId, bool $isPublic): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($categoryIds === []) {
            throw ApiKeyException::invalid('api_key.errors.grants_required', 'categories');
        }

        $categories = $this->store->findMany($categoryIds);

        foreach ($categoryIds as $id) {
            $cat = $categories[$id] ?? null;

            if ($cat === null || (int) $cat['branch_id'] !== $branchId || ! $cat['is_active']) {
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

        return $categoryIds;
    }

    // ─────────────────────── کمکی ───────────────────────

    private function isNever(string $path): bool
    {
        $segments = ApiKeyRules::segments($path);

        foreach ((array) config('api_key.never', []) as $never) {
            if (ApiKeyRules::hasPrefix($segments, ApiKeyRules::segments((string) $never))) {
                return true;
            }
        }

        return false;
    }

    /** prefix پیشنهادی برای پیام ۴۰۳: بخش‌های ثابت مسیر تا اولین پارامتر و سقف عمق */
    private function suggestPrefix(array $segments): string
    {
        $base = [];

        foreach ((array) config('api_key.route_tree.base_paths', ['api/v2']) as $candidate) {
            $candidate = ApiKeyRules::segments((string) $candidate);

            if (ApiKeyRules::hasPrefix($segments, $candidate)) {
                $base = $candidate;
                break;
            }
        }

        $depth = max(1, (int) config('api_key.route_tree.depth', 3));
        $out = $base;
        $count = 0;

        foreach (array_slice($segments, count($base)) as $segment) {
            if (str_contains($segment, '{') || $count >= $depth) {
                break;
            }

            $out[] = $segment;
            $count++;
        }

        return implode('/', $out);
    }

    /**
     * @param  array<int, array{prefix:string, level:string}>  $rules
     * @return array<int, array{prefix:string, level:string}>
     */
    private function validateRules(array $rules): array
    {
        $max = max(1, (int) config('api_key.max_rules_per_category', 30));

        if ($rules === []) {
            throw ApiKeyException::invalid('api_key.errors.category_rules_required', 'rules');
        }

        if (count($rules) > $max) {
            throw ApiKeyException::invalid('api_key.errors.too_many_rules', 'rules', ['max' => $max]);
        }

        $clean = [];

        foreach ($rules as $rule) {
            $prefix = trim((string) ($rule['prefix'] ?? ''), '/');
            $level = (string) ($rule['level'] ?? '');

            if ($prefix === '') {
                throw ApiKeyException::invalid('api_key.errors.invalid_prefix', 'rules');
            }

            if (! isset(ApiKeyRules::LEVELS[$level])) {
                throw ApiKeyException::invalid('api_key.errors.unknown_level', 'rules');
            }

            if ($this->isNever($prefix)) {
                throw ApiKeyException::invalid('api_key.errors.prefix_forbidden', 'rules');
            }

            if (! $this->routes->has($prefix)) {
                throw ApiKeyException::invalid('api_key.errors.prefix_unknown', 'rules', ['value' => mb_substr($prefix, 0, 100)]);
            }

            if (isset($clean[$prefix])) {
                throw ApiKeyException::invalid('api_key.errors.duplicate_prefix', 'rules');
            }

            $clean[$prefix] = ['prefix' => $prefix, 'level' => $level];
        }

        return array_values($clean);
    }
}
