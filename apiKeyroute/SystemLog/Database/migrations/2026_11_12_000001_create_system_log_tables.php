<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * لاگ فعالیت (AuditLog) — سه جدول 3NF:
 *   system_log_types   → جدول مرجع نوع رویداد (دسته فقط به نوع وابسته است)
 *   system_logs        → یک ردیف برای هر رویداد؛ همیشه شعبه‌دار و دائمی
 *   system_log_details → جزئیات JSON (یک‌به‌یک، اختیاری)
 *
 * عمداً FK روی branch_id/operator_id نیست: لاگ دائمی است و با حذف شعبه/اپراتور نباید پاک شود،
 * و نوع ستون id در دیتابیس‌های واقعی یکسان نیست (خطای 3780).
 *
 * نوع‌ها داده‌اند: هنگام اولین ثبت یک رویداد خودشان ساخته می‌شوند و دسته/نام نمایشی از پنل (یا SQL مهاجرت) تعیین می‌شود.
 * اجرای دستی: php artisan migrate --path=app/Services/SystemLog/Database/migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_log_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 48)->unique();
            $table->string('category', 32)->index();
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->unsignedSmallInteger('type_id');
            // شناسه‌ی موجودیتی که رویداد روی آن بوده (جایگزین goal)
            $table->string('subject_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at', 3)->useCurrent();

            $table->index(['branch_id', 'created_at'], 'idx_syslog_branch_created');
            $table->index(['branch_id', 'type_id', 'subject_id'], 'idx_syslog_branch_type_subject');
            $table->index(['operator_id', 'created_at'], 'idx_syslog_operator_created');

            $table->foreign('type_id')->references('id')->on('system_log_types')->restrictOnDelete();
        });

        Schema::create('system_log_details', function (Blueprint $table) {
            $table->unsignedBigInteger('log_id')->primary();
            $table->json('details');

            $table->foreign('log_id')->references('id')->on('system_logs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_log_details');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('system_log_types');
    }
};
