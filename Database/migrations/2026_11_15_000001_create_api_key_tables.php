<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * سرویس ApiKey: دو جدول پایه. عمداً خودکار اجرا نمی‌شود:
 *   php artisan migrate --path=app/Services/ApiKey/Database/migrations
 *
 * - api_keys         : خود کلید و هویت آن (id با چرخش عوض نمی‌شود؛ ردیف حذف نمی‌شود، فقط باطل).
 * - api_key_origins  : یک به چند؛ دامنه‌های مجاز کلید عمومی. origin در کل سیستم یکتاست (هر دامنه فقط یک کلید).
 *
 * اتصال کلید ↔ دسته (api_key_category_keys) و خود دسته‌ها در مایگریشن بعدی (2026_11_18) هستند.
 *
 * عمداً FK روی branch_id/created_by نیست (مثل SystemLog: نوع id در دیتابیس‌های واقعی یکسان نیست،
 * و کلید نباید با حذف اپراتور سازنده پاک شود). مقدارهای ثابت tinyint‌اند؛ نگاشت در App\Services\ApiKey\Domain\ApiKeyRules.
 * هیچ جدول موجودی تغییر نمی‌کند.
 */

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_keys')) {

            Schema::create('api_keys', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('هویت کلید؛ actor_id در رکورد سرویس‌ها');
                $table->unsignedBigInteger('branch_id')->comment('شعبه‌ی صاحب کلید');
                $table->string('name', 100);
                $table->string('description', 500)->nullable();
                $table->unsignedTinyInteger('type')->comment('1=secret (سرور به سرور)، 2=public (فرانت)');
                $table->unsignedTinyInteger('environment')->default(1)->comment('1=live، 2=test');
                $table->string('prefix', 32)->comment('ابتدای کلید برای نمایش در پنل، مثل erp_sk_8fK2');
                $table->char('key_hash', 64)->comment('HMAC-SHA256 کلید؛ کلید خام ذخیره نمی‌شود');
                $table->char('previous_key_hash', 64)->nullable()->comment('کلید قبلی بعد از چرخش، تا previous_key_expires_at');
                $table->timestamp('previous_key_expires_at')->nullable();
                $table->json('allowed_ips')->nullable()->comment('IP یا CIDRهای مجاز؛ null = بدون محدودیت');
                $table->unsignedInteger('rate_limit_per_minute')->nullable()->comment('null = پیش‌فرض config');
                $table->timestamp('expires_at')->nullable()->comment('null = بدون انقضا');
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->unsignedBigInteger('created_by')->comment('اپراتور سازنده (فقط ممیزی)');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('revoked_at')->nullable()->comment('null = فعال');
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->timestamps();

                $table->unique('key_hash', 'uq_api_keys_hash');
                $table->index('previous_key_hash', 'idx_api_keys_prev_hash');
                $table->index(['branch_id', 'revoked_at'], 'idx_api_keys_branch');
            });
        }
        if (!Schema::hasTable('api_key_origins')) {

            Schema::create('api_key_origins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('api_key_id');
                $table->string('origin', 255)->comment('نرمال: https://host[:port]');
                $table->timestamp('created_at')->nullable();

                $table->unique('origin', 'uq_api_key_origins_origin');
                $table->index('api_key_id', 'idx_api_key_origins_key');
                $table->foreign('api_key_id', 'fk_api_key_origins_key')->references('id')->on('api_keys')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_origins');
        Schema::dropIfExists('api_keys');
    }
};
