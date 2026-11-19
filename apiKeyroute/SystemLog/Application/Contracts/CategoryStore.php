<?php

namespace App\Services\SystemLog\Application\Contracts;

/**
 * پورت دسته‌های خطا (جدول system_log_categories؛ پیاده‌سازی: EloquentCategoryStore).
 * هر ردیف: id، kind (source|type|all)، key، title_fa، title_en، classes (فقط برای type).
 */
interface CategoryStore
{
    /** @return array<int, array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array}> */
    public function rows(): array;

    /** @return array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array} */
    public function create(string $kind, string $key, ?string $titleFa, ?string $titleEn, ?array $classes): array;

    /** تغییر نام‌ها و classes؛ false اگر دسته نباشد. kind و key هرگز عوض نمی‌شوند. */
    public function update(int $id, ?string $titleFa, ?string $titleEn, ?array $classes): bool;

    /**
     * ساخت یا به‌روزرسانی دسته‌های اولیه با شناسه‌ی مشخص (سیدر).
     *
     * @param  array<int, array{id: int, kind: string, key: string, title_fa?: ?string, title_en?: ?string, classes?: ?array}>  $rows
     */
    public function seed(array $rows): void;
}
