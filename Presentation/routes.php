<?php

use App\Services\ApiKey\Presentation\ApiKeyController;
use Illuminate\Support\Facades\Route;

/*
| پنل کلیدهای API (prefix: api/v2/api-keys، گروه console_api از ApiKeyServiceProvider).
| همه با authWithJwt؛ «اپراتور ادمین شعبه» داخل کنترلر بررسی می‌شود.
| روت‌های سرویس‌هایی که با خود کلید صدا زده می‌شوند اینجا نیستند (هر سرویس در روت‌های خودش، با middleware apiKey).
*/
Route::middleware(['authWithJwt'])->controller(ApiKeyController::class)->group(function () {
    Route::get('categories', 'categories');
    Route::get('/', 'index');
    Route::get('{id}', 'show')->whereNumber('id');

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/', 'store');
        Route::patch('{id}', 'update')->whereNumber('id');
        Route::post('{id}/rotate', 'rotate')->whereNumber('id');
        Route::delete('{id}', 'destroy')->whereNumber('id');
    });
});
