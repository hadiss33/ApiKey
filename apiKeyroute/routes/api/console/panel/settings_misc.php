<?php

use App\Http\Controllers\Api\Panel\V2\Official\Score\ScoreTransactionController;
use App\Http\Controllers\Panel\StorageController;
use App\Http\Controllers\Api\Panel\V2\Official\Notification\NotificationsController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Office\AccountingCostCenterController;
use App\Http\Controllers\Api\Panel\V2\OfficialController;
use App\Http\Controllers\Api\Panel\V2\Official\Settings\TenantSettingsController;
use App\Http\Controllers\Api\Panel\V2\Official\Settings\SettingGroupsController;
use App\Http\Controllers\Api\Panel\V2\Official\Settings\SettingsController;
use App\Http\Controllers\Api\Panel\V2\BaseController as V2BaseController;
use App\Http\Controllers\Api\B2C\V1\Passengers\PassengersScript;
use App\Http\Controllers\Api\ColleaguesController;
use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\AccommodationController;
use App\Http\Controllers\Api\Panel\V2\UserController;
use App\Http\Controllers\Api\Console\ResolveLinkController;
use App\Http\Controllers\Api\Service\AccommodationOverrideController;
use Illuminate\Support\Facades\Route;

Route::prefix('score-transactions')->controller(ScoreTransactionController::class)->middleware(['authWithJwt'])->group(function () {
    // Resource routes برای تراکنش کوین
    Route::apiResource('/', ScoreTransactionController::class)->only(['index', 'store', 'update', 'destroy']); // index, store, update, destroy
});

Route::controller(StorageController::class)->middleware(['authWithJwt'])->group(function () {
    Route::prefix('folders')->group(function () {
        Route::get('/', 'folders');
        Route::post('/', 'createFolder');
        Route::put('rename', 'renameFolder');
        Route::delete('delete', 'deleteFolder');
    });

    Route::prefix('files')->group(function () {
        Route::get('/', 'files');
        Route::post('upload', 'uploadFile');
        Route::put('rename', 'renameFile');
        Route::delete('delete', 'deleteFile');
        Route::put('move', 'moveFile');
        Route::put('download', 'downloadFile');
    });

    Route::get('storage/download', 'syncStorageBackup');
});

// روت‌های سیستم مدیریت نوتیفیکیشن‌ها (CRUD)
Route::apiResource('notifications', NotificationsController::class)->names('api.v2.notifications');

// مدیریت مراکز هزینه حسابداری
Route::prefix('accounting_cost_centers')->controller(AccountingCostCenterController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('{id}', 'show');
    Route::put('{id}', 'update');
    Route::delete('{id}', 'destroy');
});

// آزمون ها و نظرسنجی ها
Route::prefix('exam')->controller(OfficialController::class)->group(function () {
    Route::get('/get', 'getExam');
    Route::post('/response', 'responseExam');
});

Route::prefix('settings')->group(function () {
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'index']);
        Route::put('/', [TenantSettingsController::class, 'update']);

        Route::get('group/{group}', [TenantSettingsController::class, 'showGroup']);
        Route::put('group/{group}', [TenantSettingsController::class, 'updateGroup']);
    });

    Route::apiResource('groups', SettingGroupsController::class);
    Route::apiResource('definitions', SettingsController::class);
});

// اطلاعات پایه شعب
Route::prefix('config')->controller(V2BaseController::class)->group(function () {
    Route::any('/', 'config');
});

Route::get('passengers-mobile', [PassengersScript::class, 'normalizeLeftoverMobilesByReference']);
Route::post('colleagues/billing', [ColleaguesController::class, 'general_billingV2']);
Route::post('trade/national', [UserController::class, 'nationalPrefix']);
Route::get('notifications', [NotificationsController::class, 'indexPanel']);
Route::post('trade/reference/type', [BaseController::class, 'tradeReferenceType'])->middleware(['authWithJwt']);
Route::post('base/reference', [BaseController::class, 'tradeReferenceType'])->middleware(['authWithJwt']);

Route::get('accommodation/min-prices', [AccommodationController::class, 'getAccommodationMinPrices'])->middleware(['authWithJwt']);
Route::get('/resolve-link', [ResolveLinkController::class, 'resolve']);
Route::get('accommodation-overrides', [AccommodationOverrideController::class, 'get']);
Route::post('accommodation-overrides', [AccommodationOverrideController::class, 'upsert']);
