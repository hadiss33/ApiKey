<?php

/*
|--------------------------------------------------------------------------
| Console API Routes (پنل V2)
|--------------------------------------------------------------------------
|
| این فایل صرفاً یک aggregator است. روت‌های واقعی برای خوانایی و نگه‌داری
| بهتر بر اساس ماژول به فایل‌های زیر routes/api/console/panel/ تقسیم شده‌اند.
| این فایل‌ها با همان middleware/prefix ("console_api" + "api/v2") که در
| App\Providers\RouteServiceProvider برای همین console.php تعریف شده اجرا
| می‌شوند (require فقط کد را همان‌جا inline می‌کند، گروه‌بندی روتر عوض نمی‌شود).
|
| نکته‌ی مهم: ترتیب require ها همان ترتیب دقیق تعریف روت‌ها در نسخه‌ی قبلیِ
| تک‌فایلی console.php است — تغییر ترتیب می‌تواند برای مسیرهایی با الگوی
| هم‌پوشان (مثل پارامترهای wildcard) رفتار match شدن را عوض کند، پس هنگام
| افزودن/جابه‌جایی فایل جدید این ترتیب را حفظ کنید.
|
*/

require __DIR__ . '/panel/sales_trade.php';
require __DIR__ . '/panel/online_base.php';
require __DIR__ . '/panel/widgets_financial.php';
require __DIR__ . '/panel/integrations.php';
require __DIR__ . '/panel/personnel_official.php';
require __DIR__ . '/panel/accounting_documents.php';
require __DIR__ . '/panel/charter_academy.php';
require __DIR__ . '/panel/content_hub.php';
require __DIR__ . '/panel/cartable_scrumboard.php';
require __DIR__ . '/panel/settings_misc.php';
