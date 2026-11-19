<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * دسته‌ها و دسترسی به خطاها (فقط «مدیر» = ردیفی با is_manager که اولی را دستی با SQL می‌گذاریم، دسترسی می‌دهد):
 *
 *   system_log_categories         → مرجع دسته‌های خطا (داده، نه کد؛ از پنل ساخته و ویرایش می‌شود)
 *       kind  source = «کجا رخ داد» (api/job/frontend/...) | type = «چه نوعی» (database/validation/...؛ با classes)
 *             | all = ردیف ویژه‌ی «همه‌ی دسته‌ها» برای دسترسی (به هیچ خطایی نسبت داده نمی‌شود)
 *       داده‌ی اولیه با سیدر: App\Services\SystemLog\Database\Seeders\CategorySeeder (از config('system_log.categories'))
 *
 *   system_log_access             → یک ردیف به‌ازای هر اپراتور (اپراتور باید ادمین و شعبه‌ی ۱ باشد؛ در هر درخواست دوباره چک می‌شود)
 *       is_manager  مدیر: همه‌ی دسته‌ها را می‌بیند و فقط او دسترسی می‌دهد
 *       channels    JSON: sms | telegram | bale؛ اعلان هر خطای یکتای جدید در دسته‌های این کاربر
 *   system_log_access_categories  → دسته‌هایی که این اپراتور می‌بیند (چند‌به‌چند؛ دسته‌ی «all» = همه)
 *       خطا دیده می‌شود اگر source «یا» type آن در دسته‌های کاربر باشد.
 *
 *   system_errors.source          → حالا شناسه‌ی دسته (kind=source)؛ از tinyint به smallint (شناسه‌ی دسته‌ی جدید ممکن است >255 شود)
 *   system_errors.type            → جدید: شناسه‌ی دسته‌ی «چه نوعی» (kind=type). پیش‌فرض 16 = unknown. هنگام ثبت (فقط با اولین وقوع).
 *   system_log_types.title_fa/en  → نام نمایشی نوع رویداد لاگ فعالیت (قابل تعیین از پنل)
 *
 * عمداً FK روی operator_id نیست (نوع id در دیتابیس‌های واقعی یکسان نیست). روی system_errors.source/type کلید خارجی
 * به system_log_categories با مایگریشن ۰۰۰۰۰۵ اضافه می‌شود؛ جدول واسط به دسته و دسترسی FK دارد.
 * اجرای دستی: php artisan migrate --path=app/Services/SystemLog/Database/migrations
 * سپس سیدر دسته‌ها و ردیف مدیر اول (SQL: INSERT INTO system_log_access (operator_id,is_manager,is_active,channels,created_at,updated_at)
 *   VALUES (<id اپراتور>,1,1,'["sms"]',NOW(),NOW()) + یک ردیف در system_log_access_categories با category_id=99).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_log_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('kind', 8);
            $table->string('key', 32);
            $table->string('title_fa', 80)->nullable();
            $table->string('title_en', 80)->nullable();
            $table->json('classes')->nullable();

            $table->unique(['kind', 'key'], 'uq_syslogcat_kind_key');
        });

        Schema::create('system_log_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->unique();
            $table->boolean('is_manager')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('channels' , ["sms", "telegram", "bale"])->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('system_log_access_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('access_id');
            $table->unsignedSmallInteger('category_id');

            $table->primary(['access_id', 'category_id']);
            $table->foreign('access_id')->references('id')->on('system_log_access')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('system_log_categories')->restrictOnDelete();
        });

        Schema::table('system_errors', function (Blueprint $table) {
            $table->unsignedSmallInteger('source')->change();
            $table->unsignedSmallInteger('type')->default(16)->after('exception_class');

            // فیلتر دسترسی/لیست: خطاهای یک دسته، جدیدترین اول
            $table->index(['type', 'last_seen_at'], 'idx_syserr_type_last');
            $table->index(['source', 'last_seen_at'], 'idx_syserr_source_last');
        });

        Schema::table('system_log_types', function (Blueprint $table) {
            $table->string('title_fa', 120)->nullable()->after('category');
            $table->string('title_en', 120)->nullable()->after('title_fa');
        });
    }

    public function down(): void
    {
        Schema::table('system_log_types', function (Blueprint $table) {
            $table->dropColumn(['title_fa', 'title_en']);
        });

        Schema::table('system_errors', function (Blueprint $table) {
            $table->dropIndex('idx_syserr_type_last');
            $table->dropIndex('idx_syserr_source_last');
            $table->dropColumn('type');
            $table->unsignedTinyInteger('source')->change();
        });

        Schema::dropIfExists('system_log_access_categories');
        Schema::dropIfExists('system_log_access');
        Schema::dropIfExists('system_log_categories');
    }
};
