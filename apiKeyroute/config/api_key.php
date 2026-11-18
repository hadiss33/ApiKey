<?php

/*
|--------------------------------------------------------------------------
| ApiKey — تنظیمات واقعی پروژه + دسته‌بندی دسترسی‌ها
|--------------------------------------------------------------------------
| روی پیش‌فرض‌های ماژول (app/Services/ApiKey/config.php) ادغام می‌شود؛ فقط کلیدهای عوض‌شده را بنویس.
| بعد از هر تغییر: php artisan config:clear (با Docker: rebuild).
|
| ── دسته‌ها (categories) ──
| هر دسته یک prefix مسیر است. route با «دقیق‌ترین prefix» به یک دسته می‌رود و سطح لازم از متد HTTP می‌آید:
|   GET/HEAD = read    |    بقیه‌ی متدها (POST/PUT/PATCH/DELETE) = write
| route‌ای که زیر هیچ دسته‌ای نیست برای کلید بسته است. برای اتصال یک سرویس فقط یک دسته اضافه کن؛ route جدید لازم نیست.
|
| - کلید آرایه = شناسه‌ی عددی ثابت (در جدول api_key_grants ذخیره می‌شود). هرگز عوض یا دوباره استفاده نمی‌شود.
|   حذف‌کردن دسته = 'active' => false (route‌های زیر آن برای کلید بسته می‌شوند، نه اینکه به دسته‌ی کلی‌تر برگردند).
| - هر سرویس یک بازه‌ی شناسه: SystemLog = 101–199، بعدی 201–299، ...
| - levels: فقط سطح‌هایی که در این دسته معنی دارند (read و/یا write). برای هر سطح:
|     'public' => true  ← کلید عمومی (فرانت؛ کد قابل‌دیدن) هم می‌تواند این سطح را بگیرد
|     'danger'  => true ← در پنل با هشدار نمایش داده می‌شود
| - یک دسته‌ی حساس را با prefix عمیق‌تر جدا کن (مثل ingest زیر)؛ داشتن دسته‌ی کلی‌تر آن را باز نمی‌کند.
|
| ⚠ کلید حاضر، اپراتور سازنده‌اش را «بازی می‌کند» (attribute operator) و روت‌های پنل با همان منطق خودشان کار می‌کنند؛
|   پس قدرت واقعی کلید = کمترِ (دسترسی کلید، دسترسی اپراتور سازنده). سطح write روی یک prefix کلیدی را که کد قدیمی
|   روی آن route نوشتنی دارد به‌اندازه‌ی اپراتور سازنده قدرتمند می‌کند؛ public فقط برای سطح‌های بی‌خطر بگذار.
*/

return [

    // وقتی درخواست کلید ما دارد: DomainAccess/IpTrust/AuthWithJWT کنار می‌روند و خود کلید همان کارها را می‌کند
    // (branch، operator، group، level در attribute). بدون هدر کلید هیچ تغییری نیست. خاموش‌کردن: false
    'replace_panel_auth' => true,

    'groups' => [
        'system_log' => ['label' => 'api_key.groups.system_log', 'order' => 1],
    ],

    'categories' => [
        // ── SystemLog (101–199) ──
        101 => [
            'key' => 'system_log',
            'group' => 'system_log',
            'label' => 'api_key.categories.system_log',
            'prefix' => 'api/v2/system-log',                 // خطاها، لاگ فعالیت، دسترسی، دسته‌ها، نوع‌ها
            'levels' => [
                'read' => ['public' => true],
                'write' => ['danger' => true],
            ],
        ],
        102 => [
            'key' => 'system_log_ingest',
            'group' => 'system_log',
            'label' => 'api_key.categories.system_log_ingest',
            'prefix' => 'api/v2/system-log/ingest',          // ارسال خطا/لاگ از سیستم‌های بیرونی؛ فقط POST (write)
            'levels' => [
                'write' => ['public' => true],
            ],
        ],
    ],

    // قالب‌های آماده برای ساخت سریع کلید: دسته ← سطح‌ها
    'presets' => [
        'system_log_frontend' => ['label' => 'api_key.presets.system_log_frontend', 'grants' => ['system_log_ingest' => ['write'], 'system_log' => ['read']]],
        'system_log_monitoring' => ['label' => 'api_key.presets.system_log_monitoring', 'grants' => ['system_log' => ['read']]],
        'system_log_full' => ['label' => 'api_key.presets.system_log_full', 'grants' => ['system_log' => ['read', 'write'], 'system_log_ingest' => ['write']]],
    ],
];
