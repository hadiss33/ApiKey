<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * کلید خارجی system_errors.source و system_errors.type ← system_log_categories.id (RESTRICT):
 *   source = دسته‌ی «کجا رخ داد» (kind=source)، type = دسته‌ی «چه نوعی» (kind=type). حذف دسته‌ای که خطا به آن اشاره دارد ممنوع است.
 * نوع‌ها یکسان‌اند (هر دو smallint unsigned؛ مایگریشن ۰۰۰۰۰۴).
 *
 * پیش‌نیاز: جدول دسته‌ها پر باشد (سیدر CategorySeeder یا اولین ثبت خطا که خودش سیدر می‌کند) و هیچ خطایی به دسته‌ی ناموجود اشاره نکند؛
 * وگرنه این مایگریشن با پیام روشن متوقف می‌شود و داده‌ای را عوض نمی‌کند.
 * از این به بعد ثبت خطا به وجود ردیف دسته وابسته است؛ CategoryService اگر جدول را خالی ببیند خودش سیدر می‌کند.
 * اجرای دستی: php artisan migrate --path=app/Services/SystemLog/Database/migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryIds = $this->rows('system_log_categories')->newQuery()->pluck('id')->all();

        if ($categoryIds === []) {
            throw new RuntimeException('system_log_categories is empty: run App\\Services\\SystemLog\\Database\\Seeders\\CategorySeeder first.');
        }

        foreach (['source', 'type'] as $column) {
            $orphans = $this->rows('system_errors')->newQuery()->whereNotIn($column, $categoryIds)->count();

            if ($orphans > 0) {
                throw new RuntimeException("system_errors.{$column} has {$orphans} row(s) pointing to a category id that does not exist in system_log_categories; fix or delete them first.");
            }
        }

        Schema::table('system_errors', function (Blueprint $table) {
            $table->foreign('source', 'fk_syserr_source')->references('id')->on('system_log_categories')->restrictOnDelete();
            $table->foreign('type', 'fk_syserr_type')->references('id')->on('system_log_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('system_errors', function (Blueprint $table) {
            $table->dropForeign('fk_syserr_source');
            $table->dropForeign('fk_syserr_type');
        });
    }

    /** مدل موقت Eloquent برای خواندن جدول (مایگریشن به مدل‌های ماژول وابسته نمی‌شود) */
    private function rows(string $table): Model
    {
        return (new class extends Model {
            public $timestamps = false;

            protected $guarded = [];
        })->setTable($table);
    }
};
