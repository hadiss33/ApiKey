<?php

use App\Http\Controllers\Api\Panel\V2\Accounting\CentralAccounting\CentralAccountingProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| پراکسی سیستم حسابداری مرکزی (api/v2/accounting/central-accounting/{engine}/...)
|--------------------------------------------------------------------------
|
| یک روت catch-all: هر endpoint جدیدی که در سیستم مرکزی ساخته شود بدون تغییر اینجا کار می‌کند.
| محدودیت‌ها در config('central_accounting.proxy') است: allowlist engineها (فقط ledger/document/
| treasury/persons) و deny-list عملیات فقط‌مدیرسیستم. اعتبارسنجی مسیر (جلوگیری از path traversal)
| در CentralAccountingProxyController است.
| tenant = branch = office_id از دامنه‌ی درخواست می‌آید.
| این فایل از داخل گروه authWithJwt در accounting.php require می‌شود.
|
*/

$centralEngines = implode('|', array_map('preg_quote', config('central_accounting.proxy.engines', [])));

if ($centralEngines !== '') {
    Route::get('central-accounting/{engine}/{path}', [CentralAccountingProxyController::class, 'handle'])
        ->where('engine', $centralEngines)
        ->where('path', '.*');

    Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], 'central-accounting/{engine}/{path}', [CentralAccountingProxyController::class, 'handle'])
        ->where('engine', $centralEngines)
        ->where('path', '.*')
        ->middleware('throttle:30,1');
}

unset($centralEngines);
