<?php

return [
    // آدرس پایه‌ی سیستم حسابداری مرکزی، تا پیشوند نسخه (مثلاً http://s-accounting-service-fastapi:8000/api/v1/ledger-engine)
    'base_url' => rtrim((string) env('CENTRAL_ACCOUNTING_BASE_URL', ''), '/'),

    // توکن سرویس‌به‌سرویس (اختیاری؛ فقط وقتی ست باشد ارسال می‌شود)
    'service_token' => env('CENTRAL_ACCOUNTING_SERVICE_TOKEN'),

    'timeout' => (int) env('CENTRAL_ACCOUNTING_TIMEOUT', 15),
    'connect_timeout' => (int) env('CENTRAL_ACCOUNTING_CONNECT_TIMEOUT', 3),

    // فقط درخواست‌های خواندنی (GET) retry می‌شوند
    'retries' => (int) env('CENTRAL_ACCOUNTING_RETRIES', 2),
    'retry_delay_ms' => (int) env('CENTRAL_ACCOUNTING_RETRY_DELAY_MS', 200),

    'proxy' => [
        // فقط این engineها از Laravel عبور می‌کنند (messaging و media-engine عمداً نیستند)
        'engines' => ['ledger-engine', 'document-engine', 'treasury-engine', 'persons-engine'],

        // عملیات فقط‌مدیرسیستم (Laravel هدر X-System-Admin را نمی‌فرستد) و مسیرهایی که نباید از بیرون در دسترس باشند.
        // هر آیتم: [متدها, regex روی مسیر نسبی بعد از engine مثل /currencies/5/activate]
        'deny' => [
            [['POST'], '#^/currencies$#'],
            [['PUT', 'PATCH', 'DELETE'], '#^/currencies/[^/]+$#'],
            [['POST'], '#^/currencies/[^/]+/(activate|deactivate)$#'],
            [['POST'], '#^/persons/[^/]+/retry-sync$#'],
        ],
    ],
];
