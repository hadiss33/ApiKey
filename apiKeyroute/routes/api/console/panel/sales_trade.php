<?php

use App\Http\Controllers\Api\Panel\V2\BranchController;
use App\Http\Controllers\Api\Panel\V2\SalesDepartment\SalesController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\AccommodationController;
use App\Http\Controllers\Api\Panel\V2\TradeController;
use App\Http\Controllers\Api\Panel\V2\Sales\ReferenceController;
use App\Http\Controllers\Api\Panel\V2\Trade\PaymentRequest\ReferencePaymentRequestController;
use App\Http\Controllers\Api\Panel\V2\Sales\ReferenceItemController;
use App\Http\Controllers\Api\Panel\V2\Pledger\PledgerController;
use App\Http\Controllers\Api\Panel\V2\Operator\OperatorController;
use App\Http\Controllers\Api\Panel\V2\Operator\OperatorAchievementController;
use App\Http\Controllers\Api\Panel\V2\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('branch')->controller(BranchController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('launch', 'launchNewBranch');
});

Route::controller(SalesController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('sales-management/mini-list', 'miniList');
});

Route::controller(AccommodationController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('snapp-trip/hotel-list', 'getAccommodationListSnappTrip');
});

Route::controller(TradeController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('references/{type}/list', 'referenceCreditDebit');

    Route::get('logs', 'logsTrade');

    Route::prefix('pledger')->group(function () {
        Route::post('', 'storePledger');
        Route::put('', 'updatePledger');
        Route::delete('', 'deletePledger');
    });

    Route::get('flights/approved-rate', 'flightsApprovedRate');
});
// قسمت trade هنوز درست نشده برگشتیش
Route::prefix('trade')->middleware(['authWithJwt'])->group(function () {
    Route::controller(ReferenceController::class)->group(function () {
        Route::get('', 'index');
        Route::get('search', 'indexWithDetails');
        Route::post('store', 'store');
        Route::post('storetemp', 'storeTemp');
        Route::post('edit', 'update');
        Route::post('operation', 'show');
        Route::post('operation/general', 'general');
    });

    Route::apiResource('payment-requests', ReferencePaymentRequestController::class);

    Route::controller(ReferenceItemController::class)->group(function () {
        Route::post('item/edit', 'update');
        Route::post('item/add', 'store');
        Route::post('item/delete', 'delete');
        Route::post('item/value_added', 'storeValueAdded');
        Route::post('refund/update', 'updateRefund');
        Route::post('refund/delete', 'deleteRefund');
        Route::post('purchases/list', 'purchasesWebsite');
        Route::put('purchase/retry/{id}', 'purchaseWebsiteRetry');
    });

    Route::controller(TradeController::class)->group(function () {
        Route::post('cost-benefit', 'costBenefit');

        Route::post('statement', 'statementTradeApi');
        Route::post('request', 'requestTradeApi');
        Route::post('commitment', 'commitmentTrade');
        Route::post('commitment/submit', 'commitmentSubmitTrade');
        Route::post('payment-receipt', 'paymentReceiptTradeApi');

        Route::post('tickets', 'ticketsTrade')->withoutMiddleware(['authWithJwt'])->middleware(['authGuest']);
        Route::post('confirmation', 'confirmationTrade')->withoutMiddleware(['authWithJwt'])->middleware(['authGuest']);
        Route::post('contract', 'contractTradeApi')->withoutMiddleware(['authWithJwt'])->middleware(['authGuest']);
    });
});

Route::prefix('pledgers')->controller(PledgerController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'index');
    Route::get('mini-list', 'miniList');
    Route::post('/', 'store');
    Route::get('{id}', 'show');
    Route::put('{id}', 'update');
    Route::delete('{id}', 'destroy');
    Route::get('{id}/reference', 'indexReference');
});

Route::prefix('operators')->controller(OperatorController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('mini-list', 'miniList');
    Route::prefix('achievements')->controller(OperatorAchievementController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });
    Route::apiResource('achievements', OperatorAchievementController::class)->names('api.v2.achievements');
});

Route::controller(UserController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('operator/update-password', 'updatePassword');
    Route::get('get_country', 'getCitizen');
    Route::get('get_other_services', 'getOtherServices');
    Route::get('get_visa_country', 'getVisaCountry');

    Route::get('cartable-license/categories', 'cartableCategories');
    Route::get('cartable-license/requests/list', 'listCartableRequests');
    Route::post('cartable-license/request/operation', 'operationCartableRequest');
    Route::get('calendar', 'calendar');

    Route::prefix('personnel/traffic')->group(function () {
        Route::post('', 'storeTrafficPersonnel');
        Route::put('', 'updateTrafficPersonnel');
        Route::delete('', 'deleteTrafficPersonnel');
        Route::post('license', 'storeTrafficLicensePersonnel');
        Route::put('license', 'updateTrafficLicensePersonnel');
        Route::delete('license', 'deleteTrafficLicensePersonnel');
    });

    Route::post('auth/connect/disconnect', [UserController::class, 'connectDisconnect']);

    Route::post('operator', 'storeOperator');
    Route::put('operator', 'updateOperator');
    Route::get('operator', 'getOperator');
    Route::get('office-department/list', 'listOfficeDepartment');
    Route::get('operator/access', 'listAccess');
});
