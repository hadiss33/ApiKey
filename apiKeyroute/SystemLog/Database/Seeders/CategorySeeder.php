<?php

namespace App\Services\SystemLog\Database\Seeders;

use App\Services\SystemLog\Application\Services\CategoryService;
use Illuminate\Database\Seeder;

/**
 * دسته‌های اولیه‌ی خطا (جدول system_log_categories) از config('system_log.categories').
 * قابل اجرای مجدد است و دسته‌های موجود (و نام‌هایی که مدیر عوض کرده) را دست نمی‌زند.
 * اجرا: php artisan db:seed --class="App\Services\SystemLog\Database\Seeders\CategorySeeder"
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        app(CategoryService::class)->seedDefaults();
    }
}
