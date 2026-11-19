<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Trace — ردپای موقت برای دیباگ (شبیه Log::info): کلید + step + آدرس + داده.
 * کاملاً مستقل از لاگ خطا و فعالیت است و فقط ۷ روز نگه‌داری می‌شود (پاکسازی با تسک زمان‌بندی‌شده).
 * ستون key کلمه‌ی رزرو MySQL است؛ در کوئری خام باید داخل بک‌تیک بیاید (Eloquent/Schema خودش کوت می‌کند).
 * اجرای دستی: php artisan migrate --path=app/Services/SystemLog/Database/migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_traces', function (Blueprint $table) {
            $table->bigIncrements('id');
            // گروه‌بندی stepهای یک request/جاب
            $table->string('trace_id', 32);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('key', 64);
            $table->string('step', 96)->nullable();
            // آدرس درخواست (POST /api/...) یا نام جاب/دستور
            $table->string('address', 300)->nullable();
            $table->json('data')->nullable();
            $table->dateTime('created_at', 3)->useCurrent();

            $table->index(['trace_id', 'id'], 'idx_systrace_trace_id');
            $table->index(['key', 'created_at'], 'idx_systrace_key_created');
            $table->index(['branch_id', 'created_at'], 'idx_systrace_branch_created');
            $table->index('created_at', 'idx_systrace_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_traces');
    }
};
