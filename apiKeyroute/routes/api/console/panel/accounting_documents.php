<?php

use App\Http\Controllers\Api\Panel\V2\Passenger\PassengerCategoryController;
use App\Http\Controllers\Api\Panel\V2\Passenger\PassengerTagController;
use App\Http\Controllers\Api\Panel\V2\Passenger\PassengerController;
use App\Http\Controllers\Api\Panel\V2\Accounting\ManualDocument\ManualDocumentController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Export\ExportController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Document\DocumentController;
use App\Http\Controllers\Api\Panel\V2\Accounting\TransferDocumentController;
use App\Http\Controllers\Api\Panel\V2\AccountingController;
use App\Http\Controllers\Api\Panel\V2\Colleague\ColleagueController;
use App\Http\Controllers\Api\Panel\V2\Official\TestController;
use App\Http\Controllers\Api\Panel\V2\Accounting\AccountHistoryController;
use App\Http\Controllers\Api\Panel\V2\RedisAccountingController;
use App\Http\Controllers\Api\Panel\V2\BatchAccountingController;
use App\Http\Controllers\Api\Panel\V2\CoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('passengers')->middleware(['authWithJwt'])->group(function () {
    Route::prefix('categories')->controller(PassengerCategoryController::class)->group(function () {
        Route::get('mini-list', 'miniList');
        Route::post('sync-passengers', 'attachPassengersToCategory');
        Route::get('list-passenger', 'passengersInCategory');
    });
    Route::apiResource('categories', PassengerCategoryController::class)->names('api.v2.categories');

    Route::prefix('tags')->controller(PassengerTagController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });
    Route::apiResource('tags', PassengerTagController::class)->names('api.v2.tags');

    Route::controller(PassengerController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('search', 'search');
        Route::get('{passengerId}', 'show');
        Route::post('/', 'store');
        Route::put('{passengerId}', 'update');
        Route::post('add-branch/{passengersId}', 'addUserToBranch');
        Route::delete('{passengerId}', 'delete');
        Route::get('crm/manage', 'crm_passengers');
    });
});

Route::prefix('documents')->middleware(['authWithJwt'])->group(function () {
    Route::prefix('manual')->controller(ManualDocumentController::class)->group(function () {
        Route::post('/', 'store');
        Route::get('mini-list', 'miniList');
        Route::post('items/detail', 'itemsDetail');
        Route::get('/', 'index');
        Route::get('{id}', 'show');
        Route::get('{id}/edit', 'edit');
        Route::put('{id}', 'update');
        Route::delete('{id}', 'delete');
    });

    // Export endpoints
    Route::prefix('export')->group(function () {
        Route::get('/', [ExportController::class, 'index']);
        Route::post('{type}', [ExportController::class, 'create']);
        Route::get('{type}/{token}/status', [ExportController::class, 'status']);
        Route::get('{type}/{token}/download', [ExportController::class, 'download'])->name('exports.download');
        Route::get('fetch/{type}', [ExportController::class, 'fetchDocumentRows']);
    });

    Route::controller(DocumentController::class)->group(function () {
        Route::get('review/{id}', 'review');
        Route::get('details', 'details');
        Route::get('reference/{serial}', 'indexReference');
        Route::get('line/reference/{serial}', 'indexLineReference');

        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::put('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    Route::prefix('transfer')->controller(TransferDocumentController::class)->group(function () {
        Route::get('pays', 'ays');
        Route::get('manual', 'manuals');
        Route::get('sale', 'sales'); // Add and update references in accounting service
        Route::get('charter', 'charters'); // Add and update references in accounting service
    });
});

// Accounting
Route::controller(AccountingController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('banks/list', 'banksList');
    Route::get('{type}/bill/{code}', 'levelBill')->where('type', '(?!core).*');
    Route::get('account/review/{code}', 'accountReview');
    Route::post('currencies/list', 'currencies');

    Route::post('accounts/list', 'accountsList');

    Route::get('wallet/balance', 'balanceWallet');
    Route::post('wallet/check', 'checkWallet');
    Route::get('wallet/transactions', 'transactionsWallet');
    Route::patch('wallet/transactions', 'addNoteTransactionsWallet');
});

Route::prefix('colleague')->controller(ColleagueController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'index');
    Route::get('mini-list', 'miniList');
    Route::post('/', 'store');
    Route::get('{id}', 'show');
    Route::put('{id}', 'update');
    Route::delete('{id}', 'destroy');
});

Route::prefix('system-test')->controller(TestController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('job-process', 'jobProcess');
});

// Account History Routes
Route::prefix('account-history')->controller(AccountHistoryController::class)->middleware(['authWithJwt'])->group(function () {
    // محاسبه مانده‌ها
    Route::post('calculate-daily', 'calculateDailyBalanceForYear');
    Route::post('calculate-monthly', 'calculateMonthlyBalanceForYear');
    Route::post('calculate-complete', 'calculateCompleteHistory');

    // دریافت مانده‌ها
    Route::get('daily', 'getDailyBalance');
    Route::get('monthly', 'getMonthlyBalance');
    Route::get('all-daily', 'getAllDailyBalances');
    Route::get('all-monthly', 'getAllMonthlyBalances');

    // مدیریت کش
    Route::delete('cache', 'clearCache');
});

// Redis Accounting Routes
Route::prefix('redis-accounting')->controller(RedisAccountingController::class)->middleware(['authWithJwt', 'shamsiDate'])->group(function () {
    // مدیریت اسناد
    Route::post('create-missing-documents', 'createMissingDocuments');
    Route::post('save-manual-document', 'saveManualDocument');
    Route::post('save-document', 'saveDocument');
    Route::delete('delete-document', 'deleteDocument');
    Route::delete('clear-all', 'clearAllDocuments');

    // دریافت اسناد بر اساس کد
    Route::get('documents/subsidiary', 'getDocumentsBySubsidiary');
    Route::get('documents/account', 'getDocumentsByAccount');
    Route::get('documents/general', 'getDocumentsByGeneral');
    Route::get('documents/group', 'getDocumentsByGroup');
    Route::get('documents/date-range', 'getDocumentsByDateRange');

    // مانده‌ها
    Route::get('balance', 'getBalance');

    // آمار
    Route::get('stats', 'getRedisStats');
});

// Batch Redis Accounting Routes
Route::prefix('batch-accounting')->controller(BatchAccountingController::class)->middleware(['authWithJwt', 'shamsiDate'])->group(function () {
    // پردازش دسته‌ای
    Route::post('process/date-range', 'processByDateRange');
    Route::post('process/month', 'processByMonth');
    Route::post('process/year', 'processByYear');
    Route::post('process/current-month', 'processCurrentMonth');
    Route::post('process/current-year', 'processCurrentYear');

    // مدیریت سیستم
    Route::post('rebuild-indexes', 'rebuildAllIndexes');
    Route::post('optimize-memory', 'optimizeMemory');

    // گزارش‌گیری
    Route::get('report/comprehensive', 'getComprehensiveReport');
    Route::post('preview', 'previewProcessing');
});

// ویرایش اقامتگاه: علاوه بر شعبه مرکزی، با دسترسی trade.hotel_update هم مجاز است (ساخت/حذف اقامتگاه همچنان فقط مرکزی)
Route::post('core/accommodation/update', [CoreController::class, 'updateAccommodation'])
    ->middleware(['authWithJwt', 'core:trade,hotel_update']);

Route::controller(CoreController::class)->middleware(['authWithJwt', 'core'])->group(function () {
    Route::post('core/offices/list', 'officesList');
    Route::post('core/accommodations/list', 'accommodationsList');
    Route::post('core/accommodation/store', 'storeAccommodation');
    Route::delete('core/accommodation/delete', 'deleteAccommodation');
    Route::get('core/accommodation/view', 'viewAccommodation');
    Route::post('core/accommodation/supplier/store', 'supplierAccommodation');
    Route::post('core/airlines/list', 'airlinesList');
    Route::post('core/airline/store', 'airlineStore');
    Route::post('core/airline/update/{id}', 'airlineUpdate');
    Route::post('core/airports/list', 'airportsList');
    Route::post('core/airport/store', 'airportStore');
    Route::post('core/airport/update/{id}', 'airportUpdate');
    Route::post('core/socket/send', 'socketSendMessage');
    Route::get('core/changelogs', 'changeLogs');
});
