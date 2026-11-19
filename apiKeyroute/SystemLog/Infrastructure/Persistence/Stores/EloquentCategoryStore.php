<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Stores;

use App\Services\SystemLog\Application\Contracts\CategoryStore;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemLogCategory;

final class EloquentCategoryStore implements CategoryStore
{
    public function rows(): array
    {
        return SystemLogCategory::query()->orderBy('id')->get()->map(fn (SystemLogCategory $c) => $c->toRow())->all();
    }

    public function create(string $kind, string $key, ?string $titleFa, ?string $titleEn, ?array $classes): array
    {
        return SystemLogCategory::query()
            ->create(['kind' => $kind, 'key' => $key, 'title_fa' => $titleFa, 'title_en' => $titleEn, 'classes' => $classes])
            ->toRow();
    }

    public function update(int $id, ?string $titleFa, ?string $titleEn, ?array $classes): bool
    {
        $category = SystemLogCategory::query()->find($id);

        if ($category === null) {
            return false;
        }

        // null = بدون تغییر
        $category->fill(array_filter(['title_fa' => $titleFa, 'title_en' => $titleEn, 'classes' => $classes], fn ($v) => $v !== null))->save();

        return true;
    }

    public function seed(array $rows): void
    {
        // insertOrIgnore: دسته‌هایی که قبلاً هستند (و شاید مدیر نامشان را عوض کرده) دست نمی‌خورند؛ سیدر قابل اجرای مجدد است
        SystemLogCategory::query()->insertOrIgnore(array_map(fn (array $row) => [
            'id' => $row['id'],
            'kind' => $row['kind'],
            'key' => $row['key'],
            'title_fa' => $row['title_fa'] ?? null,
            'title_en' => $row['title_en'] ?? null,
            'classes' => isset($row['classes']) ? json_encode($row['classes']) : null,
        ], $rows));
    }
}
