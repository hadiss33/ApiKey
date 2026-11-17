<?php

namespace App\Services\ApiKey\Application\Services;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Support\Facades\Log;

/**
 * دسته‌بندی دسترسی‌ها از config (api_key.categories / groups / presets). جدول دیتابیس ندارند؛
 * جدول api_key_grants فقط (شناسه‌ی دسته، سطح) هر کلید را نگه می‌دارد.
 *
 * هر «دسته» یک prefix مسیر است. route با «دقیق‌ترین prefix» (بیشترین بخش مشترک از ابتدا) به دسته می‌رود،
 * و سطح لازم از متد HTTP می‌آید: GET/HEAD = read، بقیه = write. route بدون دسته برای کلید بسته است.
 * دسته‌ی منسوخ (active=false) هم در تطبیق شرکت می‌کند و رد می‌کند (تا route به دسته‌ی کلی‌تر برنگردد).
 */
final class CategoryRegistry
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $categories = null;

    /** @var array<int, array<string, mixed>>|null  بلندترین prefix اول */
    private ?array $bySpecificity = null;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->categories !== null) {
            return $this->categories;
        }

        $categories = [];
        $keys = [];
        $prefixes = [];

        foreach ((array) config('api_key.categories', []) as $id => $def) {
            $key = is_array($def) ? (string) ($def['key'] ?? '') : '';
            $segments = ApiKeyRules::segments((string) ($def['prefix'] ?? ''));
            $prefix = implode('/', $segments);
            $levels = $this->levels((array) ($def['levels'] ?? []));

            if (! is_int($id) || $id <= 0 || $id > 65535 || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)
                || $segments === [] || $levels === [] || isset($keys[$key]) || isset($prefixes[$prefix])) {
                Log::warning('ApiKey: invalid or duplicate category definition skipped', ['id' => $id, 'key' => $key, 'prefix' => $prefix]);

                continue;
            }

            $keys[$key] = true;
            $prefixes[$prefix] = true;
            $categories[$id] = [
                'id' => $id,
                'key' => $key,
                'group' => (string) ($def['group'] ?? $key),
                'label' => (string) ($def['label'] ?? $key),
                'prefix' => $prefix,
                'segments' => $segments,
                'levels' => $levels,
                'active' => (bool) ($def['active'] ?? true),
            ];
        }

        return $this->categories = $categories;
    }

    public function byKey(string $key): ?array
    {
        foreach ($this->all() as $category) {
            if ($category['key'] === $key) {
                return $category;
            }
        }

        return null;
    }

    /**
     * دسته‌ی یک route از روی «الگوی آدرس» (route->uri()، مثل api/v2/system-log/errors/{ref}): دقیق‌ترین prefix.
     * بدون دسته ← null.
     */
    public function resolve(string $uri): ?array
    {
        $segments = ApiKeyRules::segments($uri);

        foreach ($this->specificity() as $category) {
            if (ApiKeyRules::hasPrefix($segments, $category['segments'])) {
                return $category;
            }
        }

        return null;
    }

    /**
     * مجوز یک درخواست کلید: دسته‌ی route ← سطح از متد ← دسترسی کلید ← (کلید عمومی) public بودن آن سطح.
     *
     * @throws ApiKeyException scopeDenied
     */
    public function authorize(ApiKeyRecord $key, string $uri, string $method): void
    {
        $category = $this->resolve($uri);

        if ($category === null || ! $category['active']) {
            throw ApiKeyException::scopeDenied();                     // route برای کلید باز نیست
        }

        $level = ApiKeyRules::levelForMethod($method);
        $levelKey = ApiKeyRules::levelKey($level);

        if (! isset($category['levels'][$levelKey])) {
            throw ApiKeyException::scopeDenied();                     // این دسته آن سطح را ندارد (مثلاً ingest فقط write دارد)
        }

        if (! in_array($level, $key->grants[$category['id']] ?? [], true)) {
            throw ApiKeyException::scopeDenied(['category' => $category['key'], 'level' => $levelKey]);
        }

        if ($key->isPublic() && ! $category['levels'][$levelKey]['public']) {
            throw ApiKeyException::scopeDenied();
        }
    }

    /**
     * اعتبارسنجی انتخاب‌ها (+ قالب اختیاری) هنگام ساخت/ویرایش کلید.
     *
     * @param  array<string, mixed>  $grants  {"system_log": ["read","write"], ...}
     * @return array<int, array<int, int>> شناسه‌ی دسته ← شناسه‌ی سطح‌ها
     *
     * @throws ApiKeyException
     */
    public function validateGrants(array $grants, ?string $preset, bool $public): array
    {
        if ($preset !== null && $preset !== '') {
            $def = config('api_key.presets.' . $preset);

            if (! is_array($def)) {
                throw ApiKeyException::invalid('api_key.errors.unknown_preset', 'preset');
            }

            foreach ((array) ($def['grants'] ?? []) as $categoryKey => $levels) {
                $grants[$categoryKey] = array_merge((array) ($grants[$categoryKey] ?? []), (array) $levels);
            }
        }

        $clean = [];

        foreach ($grants as $categoryKey => $levels) {
            $category = $this->byKey((string) $categoryKey);

            if ($category === null || ! $category['active']) {
                throw ApiKeyException::invalid('api_key.errors.unknown_category', 'grants');
            }

            foreach ((array) $levels as $levelName) {
                $levelId = ApiKeyRules::LEVELS[(string) $levelName] ?? null;

                if ($levelId === null || ! isset($category['levels'][$levelName])) {
                    throw ApiKeyException::invalid('api_key.errors.unknown_level', 'grants');
                }

                if ($public && ! $category['levels'][$levelName]['public']) {
                    throw ApiKeyException::invalid('api_key.errors.level_not_public', 'grants');
                }

                $clean[$category['id']][$levelId] = $levelId;
            }
        }

        if ($clean === []) {
            throw ApiKeyException::invalid('api_key.errors.grants_required', 'grants');
        }

        ksort($clean);

        return array_map(fn (array $levels) => array_values($levels), $clean);
    }

    /**
     * خروجی خوانا: {"system_log": ["read","write"]} (دسته‌ی منسوخ/ناموجود حذف می‌شود).
     *
     * @param  array<int, array<int, int>>  $grants
     * @return array<string, array<int, string>>
     */
    public function grantKeys(array $grants): array
    {
        $all = $this->all();
        $out = [];

        foreach ($grants as $categoryId => $levels) {
            if (isset($all[$categoryId]) && $all[$categoryId]['active']) {
                $out[$all[$categoryId]['key']] = array_values(array_map(fn (int $l) => ApiKeyRules::levelKey($l), $levels));
            }
        }

        return $out;
    }

    /**
     * کاتالوگ برای پنل: گروه‌ها ← دسته‌ها (با prefix و سطح‌های موجود) + قالب‌ها؛ برچسب‌ها ترجمه‌شده.
     *
     * @return array{groups: array<int, array<string, mixed>>, presets: array<int, array<string, mixed>>}
     */
    public function catalog(): array
    {
        $groupDefs = (array) config('api_key.groups', []);
        $groups = [];

        foreach ($this->all() as $category) {
            if (! $category['active']) {
                continue;
            }

            $name = $category['group'];
            $groups[$name] ??= [
                'key' => $name,
                'title' => __((string) ($groupDefs[$name]['label'] ?? $name)),
                'order' => (int) ($groupDefs[$name]['order'] ?? 999),
                'categories' => [],
            ];
            $groups[$name]['categories'][] = [
                'id' => $category['id'],
                'key' => $category['key'],
                'title' => __($category['label']),
                'prefix' => $category['prefix'],
                'levels' => array_values(array_map(
                    fn (string $name, array $meta) => ['key' => $name, 'id' => ApiKeyRules::LEVELS[$name], 'public' => $meta['public'], 'danger' => $meta['danger']],
                    array_keys($category['levels']),
                    $category['levels'],
                )),
            ];
        }

        uasort($groups, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        $presets = [];

        foreach ((array) config('api_key.presets', []) as $key => $def) {
            $presets[] = [
                'key' => (string) $key,
                'title' => __((string) ($def['label'] ?? $key)),
                'grants' => (array) ($def['grants'] ?? []),
            ];
        }

        return [
            'groups' => array_values(array_map(function (array $g) {
                unset($g['order']);

                return $g;
            }, $groups)),
            'presets' => $presets,
        ];
    }

    /**
     * @param  array<string, mixed>  $levels  نام سطح ← تنظیمات ['public' => bool, 'danger' => bool]
     * @return array<string, array{public: bool, danger: bool}>
     */
    private function levels(array $levels): array
    {
        $out = [];

        foreach ($levels as $name => $meta) {
            if (! isset(ApiKeyRules::LEVELS[$name])) {
                continue;
            }

            $out[$name] = ['public' => (bool) ($meta['public'] ?? false), 'danger' => (bool) ($meta['danger'] ?? false)];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function specificity(): array
    {
        if ($this->bySpecificity === null) {
            $list = array_values($this->all());
            usort($list, fn (array $a, array $b) => count($b['segments']) <=> count($a['segments']));
            $this->bySpecificity = $list;
        }

        return $this->bySpecificity;
    }
}
