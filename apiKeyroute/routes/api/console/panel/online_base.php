<?php

use App\Http\Controllers\Api\Panel\V2\OnlineController;
use App\Http\Controllers\Api\Panel\V2\BaseController as V2BaseController;
use App\Http\Controllers\Api\Panel\V2\Notification\ContactController;
use App\Http\Controllers\Api\Panel\V2\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

Route::controller(OnlineController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('online/reservation/list', 'listOnlineItemsReservations');
    Route::get('online/reservation/penalty', 'getPenaltyOnlineItemsReservations');
    Route::post('online/reservation/refund', 'refundOnlineItemsReservations');
    Route::post('online/credit', 'credit');
    Route::post('online/credit/update', 'updateCreditSign');
    Route::post('online/reservation/penalty', 'reservationPenalty');
    Route::post('online/reservation/refund', 'reservationRefund');
    Route::post('online/search/{type}', 'routeSearch');
    Route::post('online/flight/list/date', 'flightListDate'); //تا اینجا برگشتی ها درست شد از این کنترلر
    Route::post('online/{type}/lock', 'lockItemProgress');
    Route::post('online/{type}/unlock', 'unlockItemProgress');
    Route::post('online/{type}/status', 'statusItemProgress');
    Route::get('online/item/communications', 'getItemCommunications');

    Route::get('online/accommodation/list', 'listAccommodation');
    Route::get('online/accommodation/get_min_prices', 'getAccommodationMinPrices');
    Route::get('online/accommodation/get_room_type_prices', 'getRoomTypePrices');
    Route::get('online/accommodation/get_details', 'getAccommodationDetails');
    Route::post('online/accommodation/check_status', 'checkAccommodationStatus');
});

Route::controller(V2BaseController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('base/data', 'automationBaseData');
    Route::post('titles/list', 'baseAutomationData');

    Route::post('notif/simple/list', 'notifySimpleList');
    Route::post('notif/send', 'notifySend');
    Route::post('dashboard', 'dashboardData');
    Route::get('operator/details', 'operatorDetails');
    Route::get('monitoring/callcenter', 'callCenter');
    Route::post('management/office', 'office');

    Route::post('countries-cities/get', 'getCountiesOrCities');

    Route::get('settings/index/{type}', 'settingsIndex');
    Route::put('settings/index/{type}', 'settingsUpdate');

    Route::get('base/accommodations/list', 'accommodationListBase');
    Route::get('base/accommodation/rooms/list', 'accommodationRoomsList');
    Route::post('base/accommodation/rooms/delete', 'accommodationRoomsDelete');
    Route::post('base/accommodation/rooms/update', 'accommodationRoomsUpdate');

    Route::get('base/certificates', 'indexCertificates');
    Route::get('base/certificate', 'showCertificate');
    Route::post('base/certificate', 'storeCertificate');
    Route::put('base/certificate', 'updateCertificate');
    Route::delete('base/certificate', 'deleteCertificate');

    Route::post('accommodations/list', 'accommodationsList');
    Route::get('reports/list', 'SystemReports');
});

Route::prefix('notification')->middleware(['authWithJwt'])->group(function () {
    Route::prefix('contacts')->group(function () {
        Route::controller(ContactController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/receptions', 'receptions');
        });
    });

    Route::controller(NotificationController::class)->group(function () {
        Route::get('/{type}', 'index')->where('type', 'bulks');
        Route::post('/{type}', 'store')->where('type', 'bulks');
        Route::get('/{type}/status', 'status')->where('type', 'bulks');
    });
});