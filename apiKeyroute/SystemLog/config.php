<?php

/*
 * مقدار پیش‌فرض و «نمونه»ی تنظیمات سرویس داخلی SystemLog (جایگزین Visa). از .env چیزی نمی‌خواند.
 *
 * تنظیمات واقعی در config اصلی پروژه مدیریت می‌شود: config/system_log.php. آن فایل روی همین پیش‌فرض‌ها «ادغام» می‌شود
 * (فقط کلیدهایی را بنویس که می‌خواهی عوض شوند؛ آرایه‌های لیستی مثل redact_keys کامل جایگزین می‌شوند). اگر فایل پروژه نباشد،
 * ماژول با همین پیش‌فرض‌های امن (همه خاموش) کار می‌کند؛ پس ماژول جداشدنی است.
 * نمونه‌ی آماده: php artisan vendor:publish --tag=system-log-config
 * کد برنامه فقط از config('system_log.*') می‌خواند.
 */
return [

    // مقصد ثبت خطا:
    //   visa     = سرویس بیرونی قدیمی (پیش‌فرض ایمن ماژول)
    //   internal = دیتابیس خودمان (جدول system_errors)
    // برگشت سریع: در config/system_log.php مقدار را به visa برگردانید و config:clear بزنید.
    'driver' => 'visa',

    // ثبت خطا (ErrorReport)
    'error' => [
        // تعداد خط‌های stack trace که ذخیره می‌شود
        'stack_lines' => 4,
        // اولویت با فریم‌های خود برنامه است (نه vendor)؛ اگر فریم برنامه نبود، N فریم اول برمی‌گردد
        'stack_skip_vendor' => true,
        // سقف طول پیام خطا (ستون message)
        'max_message_length' => 1000,
        // سقف طول هر مقدار رشته‌ای داخل details
        'max_string_length' => 1000,
        // سقف حجم JSON ستون details (بایت)؛ بیشتر از آن، بزرگ‌ترین کلیدها «truncated» می‌شوند
        'max_details_bytes' => 16384,
        // حداکثر تعداد exceptionهای قبلی (previous) که ثبت می‌شود
        'previous_exceptions' => 3,
        // headerهایی که از request ثبت می‌شوند (Authorization و Cookie هرگز)
        'request_headers' => ['referer', 'origin', 'content-type', 'accept', 'domain', 'x-requested-with'],

        // exceptionهایی که لاراول پیش‌فرض «گزارش نمی‌کند» (۴۰۴، ۴۰۱، ۴۰۳، ...) ولی اینجا ثبت می‌شوند (فقط با driver=internal).
        //   null = فهرست پیش‌فرض (SystemLogServiceProvider::RECORD_IGNORED؛ ValidationException و HttpResponseException عمداً بیرون‌اند)
        //   آرایه‌ی کلاس‌ها = فهرست کامل شما (جایگزین)؛ [] = هیچ‌کدام (فقط exceptionهای عادی لاراول).
        // اگر سیل ۴۰۴ ربات‌ها زیاد شد، در config/system_log.php فهرست را بدون HttpException بنویسید.
        // این‌ها فقط در SystemLog ثبت می‌شوند و مثل قبل وارد laravel.log نمی‌شوند.
        'record_ignored' => null,

        // ادغام خطاهای مشابه: ۰ = خاموش (رفتار قبلی: پیام در کلید، گروه‌بندی دائمی، همه‌ی کاربرها یک ردیف).
        // N>0 = برای هر «کاربر» از یک «نوع خطا» در یک «متد» (کلاس + فایل + خط، بدون پیام) تا N ساعت (بازه‌های ثابت تقویمی)
        //   فقط یک ردیف؛ تکرارها فقط occurrences و last_seen_at را بالا می‌برند. کاربر دیگر = ردیف خودش. بازه‌ی بعد = ردیف تازه (و اعلان تازه).
        'dedupe_hours' => 0,

        // کلاس‌هایی (و زیرکلاس‌هایشان) که با dedupe_hours>0 هم پیام در کلیدشان می‌ماند، چون از یک تابع مشترک با پیام‌های
        // متفاوت پرتاب می‌شوند و ادغام‌شان دو خطای واقعاً متفاوت را یکی می‌کرد. [] = هیچ‌کدام.
        'dedupe_keep_message' => [\App\Exceptions\ApiException::class],

        // مدت کش فهرست دسته‌ها (ثانیه). بعد از تغییر دسته از پنل، کش خودکار پاک می‌شود.
        'categories_cache_ttl' => 300,

        // پاکسازی خودکار خطاهای «حل‌شده» (کرون system-log:prune-errors؛ فقط status=resolved، نه new/investigating/ignored)
        'prune' => [
            // پیش‌فرض ماژول خاموش است (حذف داده برگشت‌پذیر نیست). روشن‌کردن: config/system_log.php
            'enabled' => false,
            // هر چند روز یک‌بار اجرا شود؛ ۱ = هر شب ساعت ۰۴:۳۰ تهران. شمارش از تقویم ثابت است (هر N روز دقیق، نه «در ماه»).
            'every_days' => 7,
            // فقط خطاهایی که حداقل این‌قدر روز از «حل‌شدن»شان گذشته؛ ۰ = همه‌ی حل‌شده‌ها
            'resolved_older_than_days' => 0,
        ],
    ],

    // دسته‌های خطا (جدول system_log_categories). این فهرست فقط «داده‌ی اولیه»ی سیدر است و وقتی جدول خالی/خراب باشد
    // جایگزین موقت می‌شود تا ثبت خطا هرگز متوقف نشود. بعد از سیدر، دسته‌ها از پنل مدیر ساخته و ویرایش می‌شوند.
    //   kind source = «کجا رخ داد» | type = «چه نوعی» (با classes: exceptionهایی که به این نوع می‌روند) | all = «همه‌ی دسته‌ها» (دسترسی)
    //   نوعی که key آن با key یک منبع یکی باشد (مثل external) وقتی کلاس exception نشناخته بود و خطا از همان منبع آمده بود برگزیده می‌شود.
    //   شناسه‌ها در system_errors.source/type ذخیره می‌شوند و باید ثابت بمانند.
    'categories' => [
        ['id' => 1, 'kind' => 'source', 'key' => 'api', 'title_fa' => 'API', 'title_en' => 'API'],
        ['id' => 2, 'kind' => 'source', 'key' => 'web', 'title_fa' => 'وب', 'title_en' => 'Web'],
        ['id' => 3, 'kind' => 'source', 'key' => 'job', 'title_fa' => 'جاب', 'title_en' => 'Job'],
        ['id' => 4, 'kind' => 'source', 'key' => 'cron', 'title_fa' => 'کرون', 'title_en' => 'Cron'],
        ['id' => 5, 'kind' => 'source', 'key' => 'console', 'title_fa' => 'کنسول', 'title_en' => 'Console'],
        ['id' => 6, 'kind' => 'source', 'key' => 'external', 'title_fa' => 'سرویس بیرونی', 'title_en' => 'External service'],
        ['id' => 7, 'kind' => 'source', 'key' => 'frontend', 'title_fa' => 'فرانت', 'title_en' => 'Frontend'],
        ['id' => 11, 'kind' => 'type', 'key' => 'validation', 'title_fa' => 'اعتبارسنجی', 'title_en' => 'Validation',
            'classes' => [\Illuminate\Validation\ValidationException::class]],
        ['id' => 12, 'kind' => 'type', 'key' => 'auth', 'title_fa' => 'احراز هویت و دسترسی', 'title_en' => 'Authentication / authorization',
            'classes' => [\Illuminate\Auth\AuthenticationException::class, \Illuminate\Auth\Access\AuthorizationException::class]],
        ['id' => 13, 'kind' => 'type', 'key' => 'business', 'title_fa' => 'کسب‌وکار', 'title_en' => 'Business'],
        ['id' => 14, 'kind' => 'type', 'key' => 'database', 'title_fa' => 'دیتابیس', 'title_en' => 'Database',
            'classes' => [\Illuminate\Database\QueryException::class, \PDOException::class]],
        ['id' => 15, 'kind' => 'type', 'key' => 'external', 'title_fa' => 'سرویس بیرونی', 'title_en' => 'External service',
            'classes' => [\Illuminate\Http\Client\RequestException::class, \Illuminate\Http\Client\ConnectionException::class, \GuzzleHttp\Exception\GuzzleException::class]],
        ['id' => 16, 'kind' => 'type', 'key' => 'unknown', 'title_fa' => 'نامشخص', 'title_en' => 'Unknown'],
        ['id' => 99, 'kind' => 'all', 'key' => 'all', 'title_fa' => 'همه‌ی دسته‌ها', 'title_en' => 'All categories'],
    ],

    // سیستم‌های بیرونی با کلید API (سرویس ApiKey؛ روت‌های api/v2/system-log/external)
    'external' => [
        // اعلان برای خطاهای ارسالی (منبع frontend/external)؛ خاموش تا خطاهای مرورگرها سیل پیامک/ربات نشوند
        'notify' => false,
        // حداکثر تعداد خطا در یک درخواست ارسال
        'max_batch' => 20,
        // سقف طول stack ارسالی کلاینت (کاراکتر)
        'max_stack_length' => 4000,
    ],

    // دسترسی به خطاها: فقط اپراتور فعال با is_admin که شعبه‌ی زیر (یا branch=[0] یعنی همه‌ی شعبه‌ها) را دارد
    // و با دامنه‌ی همین شعبه وارد شده، و در جدول system_log_access ردیف فعال دارد. فقط «مدیر» (is_manager) دسترسی می‌دهد.
    'access' => [
        'manager_branch_id' => 1,
    ],

    // اعلان خطا به کسانی که می‌توانند آن را ببینند؛ هر کاربر فقط «یک» کانال دارد (sms | telegram | bale؛ مدیر تعیین می‌کند)
    'notify' => [
        // پیش‌فرض ماژول خاموش است (پیامک هزینه دارد). روشن‌کردن: config/system_log.php
        'enabled' => false,
        // حداقل شدت برای اعلان (مقدار LogLevel: 3=warning 4=error 5=critical)
        'min_level' => 4,
        // شعبه‌ای که اعتبارنامه‌ی پنل پیامک و ربات‌هایش برای ارسال استفاده می‌شود
        'sender_branch' => 1,
        // سقف تعداد اعلان در ساعت برای هر (اپراتور، کانال)؛ ۰ = بدون سقف
        'max_per_hour' => 20,
        // سقف طول پیام خطا در متن اعلان
        'message_length' => ['sms' => 100, 'bot' => 300],
    ],

    // لاگ فعالیت (AuditLog)
    'audit' => [
        // شعبه‌ی رویدادهایی که هیچ شعبه‌ای ندارند (مثلاً ورود به کنسول؛ request بدون DomainAccess). ستون branch_id الزامی است.
        'no_branch_id' => 0,
        // دسته‌ی نوعِ رویدادی که برای اولین بار ثبت می‌شود (تا از پنل دسته‌اش تعیین شود)
        'default_category' => 'other',
        // سقف حجم JSON جزئیات (بایت). ممیزی باید کامل بماند، پس سقف سخاوتمندانه است.
        'max_details_bytes' => 65536,
        // سقف طول هر مقدار رشته‌ای داخل جزئیات
        'max_string_length' => 10000,
    ],

    // Trace: ردپای موقت دیباگ که خودمان با Trace::put() در کد می‌گذاریم (مستقل از ثبت خطا)
    'trace' => [
        // کلید خاموش/روشن سراسری. خاموش = Trace::put() فوراً برمی‌گردد و هیچ چیزی ثبت نمی‌شود.
        'enabled' => false,
        // سقف طول هر مقدار رشته‌ای داخل data
        'max_string_length' => 500,
        // سقف حجم JSON ستون data (بایت)
        'max_data_bytes' => 4096,
        // مدت نگه‌داری (روز)؛ تسک زمان‌بندی‌شده قدیمی‌تر از این را پاک می‌کند
        'retention_days' => 7,
    ],

    // فقط «رازها» پاک می‌شوند، نه داده‌ی کسب‌وکار (مبلغ، موبایل، مشخصات مسافر و ...).
    // کلید دقیقاً برابر (بدون حساسیت به حروف و با یکسان‌سازی - و _)
    'redact_keys' => [
        'authorization',
        'cookie',
        'set_cookie',
        'x_api_key',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'password',
        'password_confirmation',
        'otp',
        'private_key',
    ],

    // کلیدی که این کلمات را «داخل» خودش داشته باشد هم پاک می‌شود (مثل csrf_token، jwt_secret)
    'redact_key_contains' => [
        'password',
        'secret',
        'token',
        'authorization',
    ],
];
