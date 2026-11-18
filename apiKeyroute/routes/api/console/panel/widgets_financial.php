<?php

use App\Http\Controllers\Api\Panel\V2\WidgetController as V2WidgetController;
use App\Http\Controllers\Api\Panel\V2\CreditDebitController as V2CreditDebitController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Gatewayes\GatewayController;
use App\Http\Controllers\Api\Panel\V2\ColleaguesController as V2ColleaguesController;
use App\Http\Controllers\Api\Panel\V2\UploadController;
use Illuminate\Support\Facades\Route;

Route::controller(V2WidgetController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('dashboard/data', 'dashboardData');
    Route::get('dashboard/widget/{type}', 'dashboardWidget');
    Route::post('{space}/widget', 'upsertWidget');
    Route::get('{space}/widget', 'listWidget');
    Route::post('{space}/widget/reset', 'resetWidget');
});

Route::controller(V2CreditDebitController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('credit-debit/list', 'creditDebitList');
    Route::get('new/credit-debit/list', 'creditDebitListNEW');
    Route::post('credit-debit/summary', 'summary');

    Route::get('pay/{id}', 'showPay');
    Route::post('pay/store', 'storePay');
    Route::post('pay/update', 'updatePay');
    Route::delete('pay/trash', 'trashPay');

    Route::post('financial/items', 'financialItems');
    Route::post('check/list', 'listCheck');
    Route::post('check/operation', 'operationCheck');
    Route::post('check/operation/update', 'operationUpdateCheck');
    Route::post('financial/treeview', 'treeview');

    Route::prefix('accounting')->group(function () {
        Route::post('balance', 'accountingBalance');
        Route::post('data/copy', 'accountingDataCopy');
    });
});

// Gatewayes api list without authGuest middleware because of using in online reservation without login
Route::prefix('gateway')->controller(GatewayController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('active', 'indexActive');
    Route::post('list', 'indexGatewayBill');

    Route::post('invoice/{drive}', 'generateGatewayBill');
    Route::post('invoice/{drive}/verify', 'verifyGatewayBill');

    // This endpoint is used in online reservation without login, so we use authGuest middleware
    Route::post('details', 'showGatewayBill')->withoutMiddleware('authWithJwt')->middleware('authGuest');
});

Route::controller(V2ColleaguesController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('colleagues/list', 'colleaguesList');
    Route::post('colleague/bill', 'colleagueBill');
    Route::post('colleagues/ledger-accounts', 'colleagueLedgerAccounts');
    Route::post('colleagues/update/financial', 'updateFinacialColleagues');
    Route::post('colleagues/update/general-billing', 'getColleagueGeneralBillingUpdate');
    Route::delete('colleague/{id}', 'deleteColleague');

    Route::post('colleague/operation', 'operationColleague');
    Route::get('colleague/get', 'getColleague');
    Route::get('colleague/search', 'searchColleague');

    Route::get('colleague/users', 'indexColleagueUser');
    Route::put('colleague/user', 'updateColleagueUser');
    Route::post('colleague/user', 'storeColleagueUser');
    Route::delete('colleague/user', 'deleteColleagueUser');
});

Route::controller(UploadController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('upload', 'upload');
});
