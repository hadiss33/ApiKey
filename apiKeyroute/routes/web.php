<?php

use App\Http\Controllers\Api\Hub\V1\HubController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Gatewayes\GatewayController;
use App\Http\Controllers\Api\Panel\V2\CoreController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Panel\V2\UserController;
use App\Http\Controllers\Api\Reservation\V1\ReservationController;
use App\Http\Controllers\Api\Service\SafarmarketHotelController;
use App\Http\Controllers\Panel\TerminalController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

$domain = app()->runningInConsole() ? 'localhost' : request()->getHost();
if ($domain == 'svsh.ir') {
    $baseData = [
        "url" =>  'https://erp.savosh.com',
        "site" =>  'https://savosh.com',
        "social" =>  'https://airplus.app',
        "connection" => 'mysql',
    ];
} else if ($domain == 'mmah.ir') {
    $baseData = [
        "url" =>  'https://erp.mehromah24.com',
        "site" =>  'https://mehromah24.com',
        "social" =>  'https://www.instagram.com/mehromah.24',
        "connection" => 'mysql',
    ];
} else if ($domain == 'sr.dariasafar.com') {
    $baseData = [
        "url" =>  'https://dariasafar.com',
        "site" =>  'https://dariasafar.com',
        "social" =>  'https://dariasafar.com',
        "connection" => 'mysql',
    ];
} else if ($domain == 'aipl.ir') {
    $baseData = [
        "url" =>  'https://demo.airplus.app',
        "site" =>  'https://demo.airplus.app',
        "social" =>  'https://airplus.app',
        "connection" => 'mysql_demo',
    ];
} else if ($domain == 'smn24.ir') {
    $baseData = [
        "url" =>  'https://erp.samentour.ir',
        "site" =>  'https://samentour.ir',
        "social" =>  'https://eitaa.com/samentoor',
        "connection" => 'mysql',
    ];
} else if ($domain == '3pix.ir') {
    $baseData = [
        "url" =>  'https://erp.tripix.ir',
        "site" =>  'https://tripix.ir',
        "social" =>  'https://eitaa.com/tripix',
        "connection" => 'mysql',
    ];
} else {
    $baseData = [
        "url" =>  'https://' . $domain,
        "site" =>  'https://' . $domain,
        "social" =>  'https://' . $domain,
        "connection" => 'mysql',
    ];
}

Route::domain('ipg.airplus.app')->group(function () {
    Route::any('/payment/return', [GatewayController::class, 'returnFromGateway']);
    Route::any('/p/{slug}', [GatewayController::class, 'redirectToGateway']);
    Route::view('/', 'payment', ['name' => 'payment.index']);
});

Route::domain('gateway.ser2.ir')->group(function () {
    Route::any('/payment/return', [GatewayController::class, 'returnFromGateway']);
    Route::any('/p/{slug}', [GatewayController::class, 'redirectToGateway']);
    Route::view('/', 'payment', ['name' => 'payment.index']);
});
Route::get('/_syslog-test', fn() => throw new \RuntimeException('syslog test'));

Route::domain('bridge.airplus.app')
    ->middleware(['ipTrust', 'gzip.response'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->group(function () {
        Route::get('/', function () {
            return view('bridge', ['name' => 'bridge.index']);
        });
        Route::get('/search/{type}/{provider}', [ReservationController::class, 'search']);
        Route::post('{branch}/{provider}/{type}/search', [SafarmarketHotelController::class, 'search']);
        Route::post('{branch}/{provider}/{type}/search/without/room', [SafarmarketHotelController::class, 'searchWithoutRoom']);
        Route::post('{branch}/{provider}/{type}/details', [SafarmarketHotelController::class, 'getDetails']);
        Route::post('{branch}/{provider}/{type}/by/index', [SafarmarketHotelController::class, 'getAccommodationByIndex']);
        Route::get('{branch}/{provider}/{type}/cities', [SafarmarketHotelController::class, 'getCities']);
    });

// Base router
Route::get('/', function () {
    return view('home');
});

Route::post('/bot/telegram/support', [CoreController::class, 'botTelegramSupport']);
Route::get('/{token}/session/end', [UserController::class, 'blocking']);


// طبق درخواست: بدون روت‌های جداگانه برای هر قدم — فقط همین دو روت کنسول
// (GET/POST یکسان برای صفحه و برای تعامل)؛ همه‌ی قدم‌های بعد از ورود
// (منوی اصلی، انتخاب title کش، مقدار) از طریق همین POST /console و بر اساس
// session («console_step») از هم تفکیک می‌شوند — نه با URL جدا.
Route::controller(TerminalController::class)->middleware(['auth_console'])->group(function () {
    Route::any('cache/{type}/{data}', 'urlUpdateCache');
    Route::any('data/{type}/{data}', 'getDataFromCore');
    Route::get('console', 'consoleAutomation');
    Route::post('console', 'runMenuOption')->middleware('throttle:30,1');
});

Route::get('console/auth', function () {
    // قدم اول: دریافت شماره موبایل اپراتور برای ارسال کد از طریق بله
    return view('console', [
        'mode' => 'login',
        'clientIp' => getIp(),
        'operatorName' => null,
        'initialState' => [
            'status' => true,
            'lines'  => [],
            'next'   => ['type' => 'mobile', 'endpoint' => '/console/auth'],
        ],
    ]);
})->name('console.auth');

// همه‌ی قدم‌های ورود (موبایل/کد/رمز مشترک) هم از همین یک POST رد می‌شوند —
// نوع فیلد ارسالی (mobile/otp/password) مشخص می‌کند کدام قدم است.
// نکته: عمداً فقط POST — چون Laravel برای هر (method, URI) فقط یک روت نگه می‌دارد،
// اگر این روت به‌جای POST از any() تعریف می‌شد، هندلر GET آن روی روت نام‌دار
// 'console.auth' بالا (که فرم قدم اول را نشان می‌دهد) overwrite می‌شد.
Route::post('console/auth', [TerminalController::class, 'authenticate'])->middleware('throttle:5,1');

Route::get('/invoice/payment/{slug}', [HubController::class, 'paymentSlug']);
Route::post('/invoice/payment/return/{driver}', [HubController::class, 'paymentReturn']);

// Catch-all route برای bridge.airplus.app - باید در انتها باشد
Route::fallback(function (Request $request) {
    $host = $request->getHost();
    if ($host === 'bridge.airplus.app') {
        return safeJsonResponse([
            "status" => false,
            "time" => time(),
            "error" => [
                "code" => 404,
                "message" => '404 - The requested route was not found.'
            ],
            "support" => config('support.contact')
        ], 404);
    }
    // برای دامنه های دیگر، Laravel خودش handle کند
    return safeJsonResponse([
        "status" => false,
        "time" => time(),
        "error" => [
            "code" => 404,
            "message" => __('error.404', ['resource' => $request->path()])
        ],
        "support" => config('support.contact')
    ], 404);
});
