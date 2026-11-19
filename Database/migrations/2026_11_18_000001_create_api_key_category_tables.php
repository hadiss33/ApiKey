<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ApiKey v2: جدول‌های دسته‌بندی دینامیک (مدیر می‌سازد، در دیتابیس است، به‌ازای شعبه).
 * جدول قدیمی api_key_scopes (و api_key_grants اگر وجود داشت) حذف می‌شود.
 *
 * اجرا:
 *   php artisan migrate --path=app/Services/ApiKey/Database/migrations
 *
 * - api_key_categories      : دسته‌های هر شعبه (نام و آیا فعال است)
 * - api_key_category_rules  : قانون‌های هر دسته: یک prefix + سطح (read|write)
 * - api_key_category_keys   : جدول رابط کلید ↔ دسته (سطح در قانون‌های دسته است، نه اینجا)
 *
 * FK روی branch_id/created_by نیست (همان دلیل api_keys اصلی).
 */

return new class extends Migration
{
    public function up(): void
    {
        // جدول قدیمی dسترسی‌ها
        Schema::dropIfExists('api_key_grants');
        Schema::dropIfExists('api_key_scopes');
        if (! Schema::hasTable('api_key_categories')) {

            Schema::create('api_key_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('branch_id')->comment('شعبه‌ی صاحب این دسته');
                $table->string('name', 100)->comment('نام نمایشی (فارسی/انگلیسی)');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->comment('اپراتور سازنده (ممیزی)');
                $table->timestamps();

                $table->index(['branch_id', 'is_active'], 'idx_api_key_categories_branch');
            });
        }
        if (! Schema::hasTable('api_key_category_rules')) {

            Schema::create('api_key_category_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('category_id');
                $table->string('prefix', 200)->comment('prefix مسیر نرمال‌شده مثل api/v2/system-log');
                $table->enum('level', ['read', 'write'])->comment('read=GET/HEAD  write=بقیه‌ی متدها. write شامل read هم هست.');
                $table->timestamps();

                $table->unique(['category_id', 'prefix'], 'uq_api_key_cat_rules_cat_prefix');
                $table->index('prefix', 'idx_api_key_cat_rules_prefix');
                $table->foreign('category_id', 'fk_api_key_cat_rules_cat')->references('id')->on('api_key_categories')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('api_key_category_keys')) {

            Schema::create('api_key_category_keys', function (Blueprint $table) {
                $table->unsignedBigInteger('api_key_id');
                $table->unsignedBigInteger('category_id');

                $table->primary(['api_key_id', 'category_id'], 'pk_api_key_cat_keys');
                $table->index('category_id', 'idx_api_key_cat_keys_cat');
                $table->foreign('api_key_id', 'fk_api_key_cat_keys_key')->references('id')->on('api_keys')->cascadeOnDelete();
                $table->foreign('category_id', 'fk_api_key_cat_keys_cat')->references('id')->on('api_key_categories')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_category_keys');
        Schema::dropIfExists('api_key_category_rules');
        Schema::dropIfExists('api_key_categories');
    }
};
