<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * دسترسی کلید از «شناسه‌ی دسترسی» (api_key_scopes) به «(دسته، سطح)» (api_key_grants) تغییر کرد:
 * دسته = prefix مسیر در config/api_key.php → categories؛ سطح 1=read (GET)، 2=write (بقیه‌ی متدها).
 *
 * ⚠ داده‌ی جدول api_key_scopes منتقل نمی‌شود (معنی شناسه‌ها عوض شده)؛ کلیدهایی که قبلاً ساخته شده‌اند
 * بعد از این مایگریشن دسترسی ندارند و باید دوباره (PATCH با grants یا preset) تنظیم شوند.
 * فقط api_key_scopes عوض می‌شود؛ api_keys و api_key_origins و بقیه‌ی جدول‌های پروژه دست نمی‌خورند.
 *
 * اجرای دستی: php artisan migrate --path=app/Services/ApiKey/Database/migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_grants', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id');
            $table->unsignedSmallInteger('category_id')->comment('شناسه‌ی دسته در config/api_key.php → categories');
            $table->unsignedTinyInteger('level')->comment('1=read (GET/HEAD)، 2=write (بقیه‌ی متدها)');

            $table->primary(['api_key_id', 'category_id', 'level']);
            $table->index('category_id', 'idx_api_key_grants_category');
            $table->foreign('api_key_id', 'fk_api_key_grants_key')->references('id')->on('api_keys')->cascadeOnDelete();
        });

        Schema::dropIfExists('api_key_scopes');
    }

    public function down(): void
    {
        Schema::create('api_key_scopes', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id');
            $table->unsignedSmallInteger('scope_id')->comment('شناسه‌ی دسترسی در config/api_key.php → scopes');

            $table->primary(['api_key_id', 'scope_id']);
            $table->index('scope_id', 'idx_api_key_scopes_scope');
            $table->foreign('api_key_id', 'fk_api_key_scopes_key')->references('id')->on('api_keys')->cascadeOnDelete();
        });

        Schema::dropIfExists('api_key_grants');
    }
};
