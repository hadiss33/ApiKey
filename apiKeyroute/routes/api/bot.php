<?php

use App\Http\Controllers\Api\Bot\BotWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| روت‌های وبهوک بات‌های پیام‌رسان (تلگرام و بله)
|--------------------------------------------------------------------------
| این روت‌ها عمومی و بدون میدلور domainAccess/ipTrust هستند چون درخواست‌ها
| از سرور تلگرام/بله می‌آید، نه از دامنه‌ی خودمان. امنیت با secret_token
| هدر X-Telegram-Bot-Api-Secret-Token و شناسه‌ی غیرقابل‌حدس بات تأمین می‌شود.
*/
Route::post('{platform}/{applicationInterfaceId}', [BotWebhookController::class, 'handle'])
    ->whereIn('platform', ['telegram', 'bale'])
    ->whereNumber('applicationInterfaceId');
