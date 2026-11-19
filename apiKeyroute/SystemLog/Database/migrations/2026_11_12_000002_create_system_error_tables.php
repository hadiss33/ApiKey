<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * لاگ خطا (ErrorReport) — یک جدول: system_errors
 *
 * هر ردیف یک «خطای یکتا» است (بر اساس fingerprint که شعبه را هم شامل می‌شود). وقتی همان خطا دوباره رخ دهد
 * ردیف جدید ساخته نمی‌شود؛ شمارنده بالا می‌رود و جزئیات «آخرین وقوع» روی همین ردیف جایگزین می‌شود.
 * ثبت با یک دستور اتمیک انجام می‌شود (INSERT … ON DUPLICATE KEY UPDATE روی fingerprint)، پس بدون تراکنش
 * و قفل، حتی با requestهای همزمان درست کار می‌کند.
 *
 * مزیت: جدول فقط با تعداد خطاهای «متفاوت» رشد می‌کند، نه با تعداد وقوع‌ها (مهم، چون داده دائمی است و
 * قطع شدن یک سرویس بیرونی نباید ردیف‌های بی‌پایان بسازد).
 * هزینه: جزئیات فقط برای آخرین وقوع نگه‌داری می‌شود (مسیر، کاربر، IP، ورودی، stack)؛ شمارنده و اولین/آخرین
 * زمان می‌مانند. کد پیگیری به همین ردیف اشاره می‌کند.
 *
 * 3NF: همه‌ی ستون‌ها فقط به کلید همین ردیف (خطای یکتا) وابسته‌اند.
 *   - category از روی exception_class در کد (Enum/نگاشت Domain) مشتق می‌شود؛ ذخیره‌اش وابستگی غیرمستقیم می‌شد.
 *   - origin (client/server/upstream) از status_code و source مشتق می‌شود.
 *
 * اصول بهینه‌سازی (جدول دائمی است):
 *   - مقدارهای ثابت (status/level/source/actor_type) به‌صورت tinyint؛ نگاشت عدد ← نام در Enum کد و کامنت هر ستون.
 *   - ستون فقط-ASCII (fingerprint) با charset ascii_bin؛ ایندکس کوچک‌تر و مقایسه‌ی باینری سریع‌تر.
 *   - شناسه‌ی شعبه unsignedInteger (۴ بایت).
 *   - زمان‌ها با دقت ثانیه؛ ترتیب هم‌ثانیه‌ها با id مشخص می‌شود.
 *   - فقط دو ایندکس لیست (جدول کوچک است و هر ایندکس اضافه، سرعت upsert را کم می‌کند).
 *
 * این جدول هیچ ارتباطی با system_traces ندارد و هرگز پاک نمی‌شود (پاکسازی هفتگی فقط روی system_traces است).
 * عمداً FK روی branch_id/actor_id نیست (نوع id در دیتابیس‌های واقعی یکسان نیست).
 * اجرای دستی: php artisan migrate --path=app/Services/SystemLog/Database/migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_errors', function (Blueprint $table) {
            /*
             * ── هویت ────────────────────────────────────────────────
             */

            // کلید اصلی. کد پیگیری هم از همین id ساخته می‌شود (مثلاً «E-» + id در مبنای ۳۶)، پس ستون
            // و ایندکس جدا برای کد پیگیری لازم نیست.
            $table->bigIncrements('id');

            // شعبه‌ی صاحب خطا. کارایی: جداسازی شعبه‌ها (ادمین هر شعبه فقط خطای خودش را ببیند) و فیلتر سریع.
            // unsignedInteger (۴ بایت) چون در ایندکس تکرار می‌شود. nullable: خطای cron/console/جاب بدون request.
            $table->unsignedInteger('branch_id')->nullable();

            // کلید گروه‌بندی: md5 از شعبه + کلاس + فایل + خط + پیام نرمال‌شده (۳۲ کاراکتر hex).
            // محل (location) و کاربر در آن نیست تا یک باگ کد، یک ردیف بماند.
            // کارایی: ایندکس یکتا هم یکتایی خطا را تضمین می‌کند و هم ثبت اتمیک (upsert) را ممکن می‌کند.
            // ascii_bin: فقط hex است؛ مقایسه‌ی باینری سریع‌تر و کلید کوچک‌تر از utf8mb4. md5 کافی است (امنیتی نیست).
            $table->char('fingerprint', 32)->charset('ascii')->collation('ascii_bin')->unique();

            /*
             * ── خود خطا (ثابت بین همه‌ی وقوع‌ها؛ با اولین وقوع پر می‌شود) ──────────────
             */

            // کلاس exception (مثل QueryException). کارایی: تشخیص نوع خطا، فیلتر، و مشتق کردن دسته
            // (validation/database/external/...) در کد؛ به همین دلیل ستون category جدا نداریم.
            // nullable: خطاهای دستی (addSystemReport بدون exception) کلاس ندارند.
            $table->string('exception_class', 255)->nullable();

            // شدت خطا: 1=debug 2=info 3=warning 4=error 5=critical (Enum LogLevel).
            // tinyint چون در فیلتر لیست استفاده می‌شود و رشته‌ی تکراری فضای بی‌دلیل مصرف می‌کرد.
            $table->unsignedTinyInteger('level');

            // کد خطای برنامه (مثل tts_failed) برای خطاهایی که کد مشخص دارند. کارایی: فیلتر و گروه‌بندی معنایی.
            $table->string('code', 60)->nullable();

            // متن خطا (اولین وقوع). کارایی: نمایش در لیست.
            $table->string('message', 1000);

            // محل خطا در کد (مسیر نسبی به ریشه‌ی پروژه، نه مسیر کامل سرور). کارایی: پیدا کردن سریع خط
            // توسط برنامه‌نویس؛ هر دو هم در ساخت fingerprint هستند.
            $table->string('file', 300)->nullable();
            $table->unsignedInteger('line')->nullable();

            /*
             * ── رسیدگی ──────────────────────────────────────────────
             */

            // وضعیت رسیدگی: 1=new 2=investigating 3=resolved 4=ignored (Enum ErrorStatus).
            // کارایی: فیلتر اصلی «خطاهای باز» و جزء هر دو ایندکس لیست (tinyint = ایندکس کوچک‌تر).
            // خطای resolved که دوباره رخ دهد به new برمی‌گردد (در همان دستور upsert).
            $table->unsignedTinyInteger('status')->default(1);

            // یادداشت آزاد رسیدگی. کارایی: ثبت علت و راه‌حل برای دفعات بعد.
            $table->string('note', 1000)->nullable();

            // چه کسی و کی خطا را حل کرد. کارایی: پاسخ‌گویی. هنگام باز شدن دوباره‌ی خطا باید پاک شوند.
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->dateTime('resolved_at')->nullable();

            // آخرین زمان اعلان برای این خطا. کارایی: جلوگیری از اعلان تکراری
            // (مثلاً بله/ایمیل فقط برای خطای جدید یا برگشته).
            $table->dateTime('notified_at')->nullable();

            /*
             * ── شمارش و زمان ────────────────────────────────────────
             */

            // تعداد دفعاتی که این خطا رخ داده. کارایی: مرتب‌سازی «پرتکرارترین خطاها» و سنجش اهمیت.
            $table->unsignedInteger('occurrences')->default(1);

            // اولین زمان دیده شدن (زمان ساخته شدن ردیف). کارایی: نمایش «از کی شروع شد» و سنجش عمر خطا.
            $table->dateTime('created_at')->useCurrent();

            // آخرین زمان دیده شدن. کارایی: کلید مرتب‌سازی لیست (جدیدترین اول) و تشخیص برگشت خطا.
            $table->dateTime('last_seen_at');

            /*
             * ── عکس «آخرین وقوع» (با هر وقوع جدید جایگزین می‌شود) ──────────────
             */

            // «کجا رخ داد»: شناسه‌ی دسته‌ی kind=source در system_log_categories (1=api 2=web 3=job 4=cron 5=console
            // 6=external 7=frontend؛ دسته‌ی جدید از پنل شناسه‌ی بزرگ‌تر می‌گیرد). نه enum ثابت: مایگریشن ۰۰۰۰۰۴ آن را
            // smallint می‌کند و ستون type («چه نوعی») را کنارش می‌گذارد؛ کلید خارجی به system_log_categories با مایگریشن ۰۰۰۰۰۵.
            // کارایی: تعیین می‌کند location را چطور بخوانیم و فیلتر اصلی گزارش‌هاست.
            $table->unsignedTinyInteger('source');

            // محل رخداد در یک فیلد.
            // api/web: «POST /api/v2/trade/store» | job/cron/console: «job:ClassName» یا «cmd:name» |
            // frontend: مسیر صفحه‌ی فرانت. کارایی: درخواست را دقیق مشخص می‌کند و با
            // جست‌وجوی پیشوندی (location LIKE 'POST /api/v2/trade%') گروه‌بندی بر اساس endpoint می‌دهد.
            $table->string('location', 300)->nullable();

            // کد HTTP پاسخ. کارایی: فیلتر ۵۰۰ها و مشتق کردن origin (client/server) بدون ستون جدا.
            $table->unsignedSmallInteger('status_code')->nullable();

            // چه کسی با خطا مواجه شد: نوع 1=base 2=b2c 3=colleague 4=website و شناسه.
            // کارایی: دیدن اینکه آخرین بار چه کسی به این خطا خورد. بدون FK چون به چند جدول مختلف اشاره می‌کند.
            $table->unsignedTinyInteger('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            // IP. کارایی: شناسایی منبع شبکه‌ای و سوءاستفاده.
            $table->string('ip', 45)->nullable();

            // N خط اول stack trace خود exception (پیش‌فرض ۴، تنظیم در config/system_log.php).
            // کارایی: رسیدن به محل خطا بدون ذخیره‌ی stackهای حجیم. ستون سنگین است؛ در لیست‌ها select نشود.
            $table->text('stack')->nullable();

            // جزئیات ساختاریافته در یک JSON با کلیدهای اختیاری (پس از حذف رازها و با سقف حجم):
            //   request         → query / body / headers / مدت / حافظه‌ی درخواست (چه ورودی‌ای خطا ساخت)
            //   external        → سرویس بیرونی: نام، URL، status، پاسخ (تشخیص سریع خطای ارائه‌دهنده‌ی بیرونی)
            //   runtime         → جاب / صف / تعداد تلاش / دستور artisan یا cron (خطاهای بدون request)
            //   exception_chain → exceptionهای قبلی (علت اصلی)
            // کارایی: یک ستون به‌جای چند ستون JSON؛ هیچ‌کدام با SQL فیلتر نمی‌شوند، فقط نمایش داده می‌شوند.
            // ستون سنگین است؛ در لیست‌ها select نشود.
            $table->json('details')->nullable();

            // لیست خطاهای باز به ترتیب جدیدترین (دید ادمین سیستم)
            $table->index(['status', 'last_seen_at'], 'idx_syserr_status_last');
            // لیست خطاهای یک شعبه با فیلتر وضعیت و ترتیب جدیدترین (دید ادمین شعبه)
            $table->index(['branch_id', 'status', 'last_seen_at'], 'idx_syserr_branch_status_last');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_errors');
    }
};
