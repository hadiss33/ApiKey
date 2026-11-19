<?php

use App\Services\ApiKey\Presentation\ApiKeyController;
use App\Services\ApiKey\Presentation\CategoryController;
use Illuminate\Support\Facades\Route;

/*
| پنل کلیدهای API (prefix: api/v2/api-keys، گروه console_api از ApiKeyServiceProvider).
| همه با authWithJwt؛ «اپراتور ادمین شعبه» داخل PanelGuard بررسی می‌شود.
|   categories/tree  : درخت prefix ها از روتر (با شمارنده‌ی read/write)
|   categories       : دسته‌های شعبه (هر دسته چند قانون prefix + سطح)
|   /                : کلیدها (هر کلید به یک یا چند دسته وصل است)
| ترتیب مهم است: categories قبل از {id} کلید.
*/

Route::middleware(['authWithJwt'])->group(function () {
    Route::prefix('categories')->controller(CategoryController::class)->group(function () {
        Route::get('tree', 'tree');
        Route::get('/', 'index');
        Route::get('{id}', 'show')->whereNumber('id');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/', 'store');
            Route::patch('{id}', 'update')->whereNumber('id');
            Route::delete('{id}', 'destroy')->whereNumber('id');
        });
    });

    Route::controller(ApiKeyController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('{id}', 'show')->whereNumber('id');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/', 'store');
            Route::patch('{id}', 'update')->whereNumber('id');
            Route::post('{id}/rotate', 'rotate')->whereNumber('id');
            Route::delete('{id}', 'destroy')->whereNumber('id');
        });
    });
});
