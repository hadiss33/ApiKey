<?php

use App\Http\Controllers\Api\Panel\V2\CoreController;
use App\Http\Controllers\Api\Panel\V2\CharterController;
use App\Http\Controllers\Api\Panel\V2\Charter\Calculation\CalculationController;
use App\Http\Controllers\Api\Panel\V2\Charter\FinancialController;
use App\Http\Controllers\Api\Panel\V2\Charter\Accommodation\RoomsController;
use App\Http\Controllers\Api\Panel\V2\AcademyController;
use App\Http\Controllers\Api\Panel\V2\MailController;
use App\Http\Controllers\Api\Panel\V2\ModuleController;
use App\Http\Controllers\Api\Panel\V2\CreditCards;
use Illuminate\Support\Facades\Route;

Route::controller(CoreController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('core/accommodation/view', 'viewAccommodation');
    Route::post('core/system/report', 'addSystemReport');
});

Route::prefix('charter')->controller(CharterController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'show');
    Route::post('/', 'store');
    Route::put('/', 'operation');
    Route::patch('/', 'update');
    Route::delete('/', 'delete');
    Route::get('list', 'index');

    Route::get('communications', 'listCommunicationsCharter');
    Route::post('communications', 'operationCommunicationsCharter');

    Route::get('reservation/{type}', 'listCharterReservation');
    Route::post('reservation', 'insertListCharterReservation');
    Route::put('reservation', 'updateCharterReservation');
    Route::delete('reservation', 'deleteCharterReservation');
    Route::patch('reservation/undo', 'undoDeleteCharterReservation');
    Route::put('reservation/transfer', 'transferCharterReservation');
    Route::patch('reservation/refund', 'storeRefundCharterReservation');
    Route::patch('reservation/refund/undo', 'undoRefundCharterReservation');
    Route::delete('reservation/temporary', 'deleteTemporaryCharterReservation');
    Route::post('reservation/temporary', 'storeTemporaryCharterReservation');
    Route::post('reservation/plan/update', 'updatePlanCharterReservation');

    Route::post('warranty', 'operationWarrantyCharter');
    Route::get('warranty', 'listWarrantyCharter');

    Route::get('services/list', 'listServicesCharter');

    Route::get('flight-rate', 'getApprovedFlightRate');

    Route::get('pledger', 'listPledgerCharter');
    Route::post('pledger', 'storePledgerCharter');
    Route::delete('pledger', 'deletePledgerCharter');

    Route::get('tour/stats', 'listTourStats');
    Route::post('tour/stats', 'storeTourStats');
    Route::put('tour/stats/{id}', 'updateTourStats');
    Route::get('tour/stats/{id}', 'showTourStats');
    Route::delete('tour/stats/{id}', 'deleteTourStats');
});

Route::prefix('charter/calculation')->controller(CalculationController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('accommodation/mini-list/{id}', 'accommodationMiniList');
});

Route::prefix('charter/financial')->controller(FinancialController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'index');
    Route::get('{id}', 'show');
    Route::get('completion', 'completion');
    Route::patch('completion', 'completionUpdate');
});

Route::prefix('charter/accommodation/rooms')->controller(RoomsController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('/', 'storeGroup');
    // Route::post('/', 'show');
    Route::post('access', 'access');
    Route::get('{id}', 'index');
});

Route::controller(AcademyController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('academy/categories/view', 'viewCategories');
    Route::get('academy/courses/view', 'viewCourses');
    Route::get('academy/course/steps/view', 'viewStepsCourse');
    Route::post('academy/course/steps/update', 'updateStepsCourse');
});

Route::controller(MailController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('mail/servers/list', 'mailServer');
    Route::get('mail/address/list', 'mailAddress');
    Route::post('mail/address/store', 'mailAddressStore');
    Route::get('mail/{type}/list', 'mailBox');
    Route::post('mail/{type}/update', 'mailUpdate');
    Route::post('mail/attachments/download', 'mailAttachmentsDownload');
    Route::get('mail/sent/get', 'mailSentGet');
    Route::post('mail/sent/store', 'mailSentStore');
    Route::delete('mail/sent/trash', 'mailSentTrash');
    Route::get('mail/inbox/get', 'mailInboxGet');
    Route::delete('mail/inbox/trash', 'mailInboxTrash');
    Route::delete('mail/trash/delete', 'mailTrashDelete');
});

Route::controller(ModuleController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('modules/lottery/registrations', 'lotteryRegistrations');
    Route::post('modules/lottery/description/update', 'lotteryDescriptionUpdate');
});

Route::controller(CreditCards::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('credit-card', 'createCreditCard');
    Route::get('credit-card/index', 'indexCreditCard');
    Route::get('credit-card', 'showCreditCard');
});
