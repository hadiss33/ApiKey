<?php

use App\Http\Controllers\Api\Panel\V2\Operator\OperatorController;
use App\Http\Controllers\Api\Panel\V2\Official\Attendance\AttendanceController;
use App\Http\Controllers\Api\Panel\V2\OfficialController;
use App\Http\Controllers\Api\Panel\V2\Official\ShiftWork\ShiftWorkController;
use App\Services\Official\OperatorPerformance\Call\Application\Controllers\CallPerformanceController;
use App\Http\Controllers\Api\Panel\V2\Support\SupportController;
use App\Http\Controllers\Api\Panel\V2\ShortLinkController;
use App\Http\Controllers\Api\Panel\V2\Announcement\AnnouncementController;
use App\Http\Controllers\Api\Panel\V2\Announcement\AnnouncementTaxInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('personnel')->middleware(['authWithJwt'])->group(function () {
    Route::post('/', [OperatorController::class, 'store']);
    Route::get('list',  [OperatorController::class, 'index']);

    Route::prefix('attendance')->controller(AttendanceController::class)->group(function () {
        Route::post('face', 'storeFace');
        Route::post('/', 'store');
        Route::get('/', 'report');
        Route::post('{id}/face', 'updateFace');
        Route::get('{id}/detail', 'showDetail');
        Route::get('permission', 'permission');
    });

    Route::controller(OfficialController::class)->group(function () {
        Route::post('bill', function () { // Route::post('bill', 'personnelBill');
            return response()->json([
                'error' => [
                    'code' => 404,
                    'message' => 'این درخواست دیگر معتبر نمی باشد',
                ],
                'meta' => [
                    'timestamp' => time(),
                ],
            ], 404);
        });
        Route::post('operation', 'operationPersonnel');
        Route::get('access-list', 'personnelAccessList');

        Route::post('tasks/category/operation', 'operationTasksCategory');
        Route::get('tasks/categories/list', 'listTasksCategories');
        Route::get('tasks/list', 'listTasks');
        Route::post('tasks/task/operation', 'operationTask');
        Route::get('tasks/task/details', 'getTasksDetails');

        Route::get('exams/list', 'listExams');
        Route::get('exams/answers/list', 'listExamsAnswers');
        Route::patch('exams/answers/list/follow-up', 'listExamsAnswersFollowUp');
        Route::get('exams/answers/evaluation', 'listExamsEvaluation');

        Route::get('salary/annual-obligation', 'salaryAnnualObligation');

        Route::get('scheduled/notifications', 'listScheduledNotifications');
        Route::post('scheduled/notifications', 'storeScheduledNotifications');
        Route::put('scheduled/notifications', 'updateScheduledNotifications');

        Route::post('official/titles/copy', 'officialTitlesCopy');
        Route::post('official/insert/levels', 'officialInsertLevelBase');
    });

    Route::prefix('shift')->controller(ShiftWorkController::class)->middleware(['authWithJwt'])->group(function () {
        Route::get('mini-list', 'miniList');
        Route::get('list', 'index');
        Route::get('/{shift}', 'show');
        Route::post('/', 'store');
        Route::put('/{shift}', 'update');
        Route::delete('/{shift}', 'destroy');
        Route::patch('reset/{shift}', 'reset');
    });

    Route::prefix('{type}')->controller(OperatorController::class)->group(function () {
        Route::get('/', 'getTypeValue');
        Route::put('/', 'updateTypeValue');
    });
});

Route::prefix('official')->middleware(['authWithJwt'])->group(function () {
    Route::prefix('operator-performance')->controller(CallPerformanceController::class)->group(function () {
        Route::get('calls', 'index');
    });
});

Route::prefix('support')->controller(SupportController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('departments/list', 'listDepartments');
    Route::get('department/members', 'listDepartmentMembers');
    Route::get('department/questions/list', 'listQuestionsDepartment');
    Route::get('tickets', 'index');
    Route::post('ticket/update', 'update');
    Route::patch('ticket/notice', 'noticeNote');
    Route::get('ticket/notes/list', 'indexNote');
    Route::post('ticket/note/store', 'store');
    Route::delete('ticket/delete', 'delete');
});

// Short link creation API
Route::controller(ShortLinkController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('short-links', 'store');
});

Route::prefix('announcement')->controller(AnnouncementController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/', 'index');
    Route::get('company', 'indexCompany');
    Route::get('check', 'check');
    Route::get('{announcementId}', 'show');
    Route::post('/', 'store');
    Route::put('{announcementId}', 'update');
    Route::get('{announcementId}/edit', 'edit');
    Route::post('statement', 'statement');
    Route::post('ledger', 'ledger');
    Route::post('calculation', 'calculation');
    Route::get('communications/diff', 'diffCommunications');
    Route::post('communications/sync', 'syncCommunications');
    Route::get('communications/script', 'scriptForCommunication');
    Route::apiResource('tax-invoices', AnnouncementTaxInvoiceController::class)
        ->parameters(['tax-invoices' => 'announcementTaxInvoice',])->only(['store', 'show', 'update', 'destroy']);
});
