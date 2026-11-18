<?php

use App\Http\Controllers\Api\Panel\V2\Articles\ArticleController;
use App\Http\Controllers\Api\Panel\V2\Articles\CategoryController;
use App\Http\Controllers\Api\Panel\V2\Articles\TagController;
use App\Http\Controllers\Api\Panel\V2\Articles\LandingPageController;
use App\Http\Controllers\Api\Panel\V2\Articles\PageMetatagController;
use App\Http\Controllers\Api\Panel\V2\Articles\PlaceController;
use App\Http\Controllers\Api\Panel\V2\MediaController;
use App\Http\Controllers\Api\Panel\V2\TravelRequestsController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\FacilityController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\FacilityCategoryController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\RuleController;
use App\Http\Controllers\Api\Panel\V2\CommentController;
use App\Http\Controllers\Api\Hub\V1\HubController;
use App\Http\Controllers\Api\Panel\V2\BaseController as V2BaseController;
use App\Http\Controllers\Api\Panel\V2\CoreController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Panel\V2\TravelRequestMessagesController;

Route::middleware(['authWithJwt'])->group(function () {
    Route::post('articles/{id}/views', [ArticleController::class, 'incrementViews']);
    Route::resource('articles', ArticleController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('tags', TagController::class);
    Route::resource('landing_pages', LandingPageController::class);
    Route::resource('page_metatags', PageMetatagController::class);
    Route::resource('places', PlaceController::class);

    Route::resource('media', MediaController::class);
    Route::get('travel_requests', [TravelRequestsController::class, 'index']);
    Route::post('travel_requests/status/{id}', [TravelRequestsController::class, 'changeStatus']);
    Route::prefix('travel-requests/{travelRequestId}/messages')->group(function () {
        Route::get('/', [TravelRequestMessagesController::class, 'index']);
        Route::post('/', [TravelRequestMessagesController::class, 'store']);
        Route::delete('/{id}', [TravelRequestMessagesController::class, 'destroy']);
    });
});

Route::prefix('accommodation')->middleware(['authWithJwt'])->group(function () {
    Route::resource('facilities', FacilityController::class);
    Route::resource('facilities_category', FacilityCategoryController::class);
    Route::resource('rules', RuleController::class);
});

Route::middleware(['authWithJwt'])->group(function () {
    Route::get('comments', [CommentController::class, 'index']);
    Route::get('comments/{comment}', [CommentController::class, 'show']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('comments', [CommentController::class, 'store'])->middleware(['decryptData']);
    Route::put('comments/{comment}', [CommentController::class, 'update'])->middleware(['decryptData']);

    Route::post('comments/{commentId}/{action}', [CommentController::class, 'likeOrDislike']);
    Route::delete('comments/{commentId}/{action}', [CommentController::class, 'removeLikeOrDislike']);
});

Route::middleware(['authWithJwt'])->controller(HubController::class)->group(function () {
    Route::post('invoice/process', 'createInvoice');
    Route::post('invoice/payment/details', 'paymentSlugData');
    Route::post('invoice/payment/wallet', 'payByWallet');

    Route::get('core/bill/{type}', 'indexBranchBill');

    Route::get('core/hub/information', 'getBasicInformation');
    Route::get('core/hub/analyze', 'hubAnalysis');
    Route::get('core/hub/reservation', 'hubReservation');
    Route::post('core/hub/reservation', 'hubStoreReservation');
    Route::patch('core/hub/reservation/refund', 'hubRefund');
    Route::patch('core/hub/reservation/refund/undo', 'hubUndoRefund');
});

Route::middleware(['authWithJwt'])->group(function () {
    Route::post('flights/ticket/information/{type}', [V2BaseController::class, 'ticketInformation']);
    Route::post('flights/routes/update', [V2BaseController::class, 'updateAirlineActiveRoute']);
    Route::post('flights/routes/min_price', [V2BaseController::class, 'getRouteFlightsMinPrice']);

    Route::get('flights/prices/min/day', [V2BaseController::class, 'getMinPricesPerDay']);
});

Route::controller(CoreController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('core/application_interface', 'listApplicationInterface');
    Route::get('core/application_interface/{id}', 'showApplicationInterface');
    Route::delete('core/application_interface/{id}', 'deleteApplicationInterface');
    Route::post('core/application_interface', 'storeApplicationInterface');
    Route::put('core/application_interface/{id}', 'updateApplicationInterface');
    Route::get('core/application_interface_types', 'listApplicationInterfacTypes');
    Route::get('core/application_interface_services', 'listApplicationInterfacServices');
    Route::get('core/application_interface_object_types', 'listApplicationInterfacObjectType');
    Route::put('core/application_interface/status/{id}', 'updateStatusApplicationInterface');
});
