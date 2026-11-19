<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\CategoryStore;
use App\Services\SystemLog\Domain\Access\AccessException;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * دسته‌های خطا («کجا» و «چه نوعی» و «همه»): تنها منبع حقیقت جدول system_log_categories است.
 *
 * taxonomy() فهرست را با کش کوتاه می‌دهد و هرگز شکست نمی‌خورد: جدول خالی ← خودش سیدر می‌شود (system_errors.source/type کلید خارجی دارند)؛
 * کش یا دیتابیس خراب ← فهرست اولیه‌ی config جایگزین می‌شود. ساخت/ویرایش دسته فقط از پنل مدیر (کنترل دسترسی با فراخوان).
 */
final class CategoryService
{
    private const CACHE_KEY = 'system_log:categories';

    public function __construct(private readonly CategoryStore $store) {}

    public function taxonomy(): ErrorTaxonomy
    {
        return new ErrorTaxonomy($this->rows());
    }

    /**
     * @param  array<int, string>|null  $classes
     * @return array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array}
     */
    public function create(string $kind, string $key, ?string $titleFa, ?string $titleEn, ?array $classes): array
    {
        if (! in_array($kind, [ErrorTaxonomy::SOURCE, ErrorTaxonomy::TYPE], true) || ! preg_match('/^[a-z][a-z0-9_]{1,31}$/', $key)) {
            throw AccessException::invalid('system_log.category.invalid');
        }

        if ($this->taxonomy()->idOrNull($kind, $key) !== null) {
            throw AccessException::invalid('system_log.category.exists');
        }

        $created = $this->store->create($kind, $key, $titleFa, $titleEn, $kind === ErrorTaxonomy::TYPE ? $this->classes($classes) : null);
        $this->forget();

        return $created;
    }

    /** @param  array<int, string>|null  $classes */
    public function update(int $id, ?string $titleFa, ?string $titleEn, ?array $classes): bool
    {
        $row = $this->taxonomy()->find($id);

        if ($row === null) {
            return false;
        }

        $ok = $this->store->update($id, $titleFa, $titleEn, $row['kind'] === ErrorTaxonomy::TYPE ? $this->classes($classes) : null);
        $this->forget();

        return $ok;
    }

    /** نوشتن فهرست اولیه‌ی config در جدول (سیدر) */
    public function seedDefaults(): void
    {
        $this->store->seed((array) config('system_log.categories'));
        $this->forget();
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $rows = $this->read();

        // جدول در دسترس است ولی خالی: کلید خارجی system_errors به ردیف دسته نیاز دارد، پس فهرست اولیه خودش سیدر می‌شود
        // (insertOrIgnore؛ idempotent) تا اولین خطا ثبت‌نشده نماند.
        if ($rows === []) {
            try {
                $this->seedDefaults();
            } catch (Throwable) {
                // جدول در دسترس نیست؛ فهرست config جایگزین می‌شود
            }

            $rows = $this->read();
        }

        $hasUnknown = array_filter($rows, fn ($r) => ($r['kind'] ?? null) === ErrorTaxonomy::TYPE && ($r['key'] ?? null) === ErrorTaxonomy::UNKNOWN) !== [];

        return $hasUnknown ? $rows : (array) config('system_log.categories');
    }

    /** ردیف‌های دسته با کش؛ خطا/خالی ← [] (و «خالی» هرگز در کش نمی‌ماند) */
    private function read(): array
    {
        try {
            $rows = (array) Cache::remember(self::CACHE_KEY, (int) config('system_log.error.categories_cache_ttl', 300), fn () => $this->store->rows());
        } catch (Throwable) {
            try {
                return $this->store->rows();
            } catch (Throwable) {
                return [];
            }
        }

        if ($rows === []) {
            $this->forget();
        }

        return $rows;
    }

    /**
     * @param  array<int, string>|null  $classes
     * @return array<int, string>|null
     */
    private function classes(?array $classes): ?array
    {
        $classes = array_values(array_unique(array_map('trim', (array) $classes)));

        foreach ($classes as $class) {
            if (! preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class)) {
                throw AccessException::invalid('system_log.category.invalid');
            }
        }

        return $classes === [] ? null : array_map(fn (string $c) => ltrim($c, '\\'), $classes);
    }

    private function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // کش خراب است؛ با انقضای TTL خودش درست می‌شود
        }
    }
}
