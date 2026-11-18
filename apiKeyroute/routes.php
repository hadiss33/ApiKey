<?php

use App\Services\SystemLog\Presentation\Http\Controllers\AccessController;
use App\Services\SystemLog\Presentation\Http\Controllers\AuditLogController;
use App\Services\SystemLog\Presentation\Http\Controllers\CategoryController;
use App\Services\SystemLog\Presentation\Http\Controllers\ErrorController;
use App\Services\SystemLog\Presentation\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

/*
| پنل SystemLog (prefix: api/v2/system-log، گروه console_api از SystemLogServiceProvider).
| همه با authWithJwt؛ دسترسی هر endpoint داخل سرویس‌ها (AccessService) بررسی می‌شود، نه اینجا.
| Trace پنل ندارد (ابزار برنامه‌نویس: Trace::put).
|
| کلید API (سرویس ApiKey) همین route‌ها را بدون تعریف دوباره صدا می‌زند: دسته‌ها در config/api_key.php → categories
| (prefix api/v2/system-log ← GET=read، بقیه=write؛ و api/v2/system-log/ingest ← فقط write). وقتی کلید حاضر است
| DomainAccess/IpTrust/AuthWithJWT اجرا نمی‌شوند و ApiKeyAuth همان attributeها (branch، operator، ...) را می‌گذارد.
*/
Route::middleware(['authWithJwt'])->group(function () {
    // دریافت خطا/لاگ از سیستم‌های بیرونی (فقط کلید API؛ درخواست اپراتور ۴۰۳ می‌گیرد). prefix جدا = دسته‌ی جدا برای کلید عمومی
    Route::post('ingest/errors', [IngestController::class, 'reportErrors']);
    Route::post('ingest/audit-logs', [IngestController::class, 'storeAudit']);

    // دسترسی به خطاها
    Route::get('access/me', [AccessController::class, 'me']);
    Route::get('access', [AccessController::class, 'index']);
    Route::put('access/{operatorId}', [AccessController::class, 'update'])->whereNumber('operatorId');
    Route::delete('access/{operatorId}', [AccessController::class, 'destroy'])->whereNumber('operatorId');

    // دسته‌های خطا
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::patch('categories/{id}', [CategoryController::class, 'update'])->whereNumber('id');

    // خطاها
    Route::get('errors', [ErrorController::class, 'index']);
    Route::get('errors/{ref}', [ErrorController::class, 'show']);
    Route::patch('errors/{ref}/status', [ErrorController::class, 'updateStatus']);

    // لاگ فعالیت و نوع‌های آن
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('types', [AuditLogController::class, 'types']);
    Route::patch('types/{id}', [AuditLogController::class, 'updateType'])->whereNumber('id');
});
