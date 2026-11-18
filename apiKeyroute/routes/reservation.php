<?php

use App\Http\Controllers\Api\Reservation\V1\BaseController;
use App\Http\Controllers\Api\Reservation\V1\FinancialController;
use App\Http\Controllers\Api\Reservation\V1\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reservation API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(ReservationController::class)->group(function () {
    Route::get('/ip', function () {
        return response()->json([
            'payload' => [
                "host" => getHostIp(),
                "ip" => getIp()
            ],
            'meta' => [
                'timestamp' => time()
            ]
        ], 200);
    });
    Route::get('/error-handling', function () {
        return response([
            "status" => true,
            "time" => time(),
            "data" => [
                "host" => getHostIp(),
                "ip" => getIp()
            ]
        ], 200);
    });
    // Route::get("/search/{type}/{provider}", "search")->middleware('ipTrust');
    Route::get("/items/status", "itemsStatus");
    Route::post("/lock", "lock");
    Route::post("/unlock", "unlock");
    Route::post("/book", "book");
    Route::get("/book/status", "bookStatus");
    Route::get("/book/history", "bookHistory");
    Route::post("/refund", "refund");
    Route::get("/refund/procedure", "refundProcedure");
    Route::post("/change", "change");
    Route::get("/change/procedure", "changeProcedure");
    Route::get("/data/{type}/{goal}", "getData");
});

Route::controller(FinancialController::class)->group(function () {
    Route::get("/financial/history", "financialHistory");
    Route::get("/credit/history", "creditHistory");
});

Route::controller(BaseController::class)->group(function () {
    Route::get("/credit", "credit");
    Route::get("/errors", "getErrorDetails");
    Route::post("/link/create", "linkCreate");
    Route::get("/report/account", "reportAccount");
    Route::post("/auth/token", "authToken");
});

Route::fallback(function (Request $request) {
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
