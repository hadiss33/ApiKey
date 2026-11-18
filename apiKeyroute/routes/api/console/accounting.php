<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Panel\V2\Accounting\Accounts\AccountController;
use App\Http\Controllers\Api\Panel\V2\Accounting\CentralAccounting\CentralDocumentController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Checks\CheckController;
use App\Http\Controllers\Api\Panel\V2\Accounting\DocumentLink\DocumentLinkController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Generals\GeneralController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Groups\GroupController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Levels\LevelController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Moeens\MoeenController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Office\AccountingCostCenterController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Products\ProductIntaCode\ProductIntaCodeController;
use App\Http\Controllers\Api\Panel\V2\Accounting\Products\ProductsController;
use App\Http\Controllers\Api\Panel\V2\AccountingController;

/*
|--------------------------------------------------------------------------
| Console Accounting API Routes
|--------------------------------------------------------------------------
|
| روت‌های API ماژول حسابداری پنل کنسول نسخه ۲.
| تمام روت‌ها نیازمند احراز هویت JWT هستند.
|
| ساختار ماژول‌ها:
|  - groups / generals / moeens / levels : داده‌های پایه درختی حساب‌ها
|  - preference   : تنظیمات ترجیحی سطوح
|  - checks       : مدیریت چک‌ها
|  - accounts     : لیست کوتاه حساب‌ها
|  - account      : CRUD حساب در نمای درختی
|  - connections  : مدیریت اتصالات بین حساب‌ها
|  - closing      : حساب‌های بستن دوره مالی
|  - inta-code    : کدهای اینتا محصولات
|  - products     : مدیریت محصولات حسابداری
|  - review       : خروجی گزارش بررسی مالی
|  - cost-centers : مراکز هزینه
|  - document-links : پیوند اسناد دفتر ریز حساب
|  - central-accounting/documents : لیست اسناد سیستم حسابداری مرکزی (فقط‌خواندنی)
|  - central-accounting/{engine}/... : پراکسی همه‌ی API های سیستم مرکزی (central_accounting.php)
|
*/

// تمام روت‌های این فایل نیازمند توکن JWT معتبر هستند
Route::middleware(['authWithJwt'])->group(function () {

    // --- داده‌های پایه درختی حساب‌ها ---

    // لیست کوتاه گروه‌های حسابداری
    Route::prefix('groups')->controller(GroupController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });

    // لیست کوتاه حساب‌های کل
    Route::prefix('generals')->controller(GeneralController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });

    // لیست کوتاه حساب‌های معین
    Route::prefix('moeens')->controller(MoeenController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });

    // لیست کوتاه سطوح حسابداری
    Route::prefix('levels')->controller(LevelController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });

    // --- تنظیمات ترجیحی سطوح --- CRUD کامل
    Route::prefix('preference')->controller(LevelController::class)->group(function () {
        Route::get('/', 'index');          // لیست تنظیمات
        Route::post('/', 'store');         // ایجاد تنظیم جدید
        Route::get('{id}', 'show');       // نمایش تنظیم
        Route::put('{id}', 'update');     // به‌روزرسانی تنظیم
        Route::delete('{id}', 'destroy'); // حذف تنظیم
    });

    // --- مدیریت عملیات چک‌ها ---
    Route::prefix('checks')->controller(CheckController::class)->group(function () {
        Route::prefix('operation')->group(function () {
            Route::get('mini-list', 'miniListOperation'); // لیست کوتاه چک‌ها
            Route::get('level5/mini-list', 'miniListLevel5'); // لیست کوتاه چک‌ها

            Route::post('{checkId}', 'storeOperation');   // ثبت چک جدید
            Route::get('{checkId}', 'indexOperation');    // لیست عملیات های چک‌
            Route::put('{checkId}', 'updateOperation');   // به‌روزرسانی چک
            Route::delete('{checkId}', 'destroyOperation');    // حذف چک
        });
    });

    // --- لیست کوتاه حساب‌های تفصیلی ---
    Route::prefix('accounts')->controller(AccountController::class)->group(function () {
        Route::get('mini-list', 'miniList');
    });

    // --- CRUD حساب در نمای درختی ---
    Route::prefix('account')->controller(AccountingController::class)->group(function () {
        Route::get('/', 'accountIndex');                   // لیست حساب‌ها
        Route::post('/', 'accountStore');                  // ایجاد حساب جدید
        Route::put('/', 'accountUpdate');                  // به‌روزرسانی حساب
        Route::delete('/', 'accountDelete');               // حذف حساب
        Route::post('update', 'updateAccountInTreeView'); // به‌روزرسانی حساب در نمای درختی
        Route::get('get', 'getAccountInTreeView');        // دریافت حساب از نمای درختی
    });

    // --- مدیریت اتصالات بین حساب‌ها ---
    // توجه: تمام عملیات با POST انجام می‌شود (از جمله view و list)
    Route::prefix('connections')->controller(AccountingController::class)->group(function () {
        Route::post('store', 'storeConnection');   // ایجاد اتصال جدید
        Route::post('update', 'updateConnection'); // به‌روزرسانی اتصال
        Route::post('trash', 'trashConnection');   // انتقال اتصال به سطل زباله
        Route::post('merge', 'mergeConnection');   // ادغام دو اتصال
        Route::post('view', 'viewConnection');     // نمایش جزئیات اتصال
        Route::post('list', 'listConnection');     // لیست اتصالات
    });

    // --- بستن دوره مالی ---
    Route::prefix('closing')->controller(AccountingController::class)->group(function () {
        Route::get('/', 'showClosingAccount');                              // نمایش وضعیت بستن حساب
        Route::post('/', 'updateClosingAccount');                           // به‌روزرسانی بستن حساب
        Route::put('definite/{type}', 'definiteDocumentsInClosingAccount'); // قطعی کردن اسناد بستن دوره
    });

    // --- کدهای اینتا محصولات ---
    Route::prefix('inta-code')->controller(ProductIntaCodeController::class)->group(function () {
        Route::get('/', 'index');             // لیست کامل کدهای اینتا
        Route::get('mini-list', 'miniList'); // لیست کوتاه کدهای اینتا
    });

    // --- مدیریت محصولات حسابداری ---
    Route::prefix('products')->controller(ProductsController::class)->group(function () {

        // محصولات فعال — زیرگروه جداگانه با prefix 'active'
        Route::prefix('active')->group(function () {
            Route::get('/', 'indexActive');                  // لیست محصولات فعال
            Route::put('{productId}', 'updateActive');      // تغییر وضعیت فعال بودن
            Route::get('mini-list', 'miniListActive');      // لیست کوتاه محصولات فعال
        });

        // CRUD کامل محصولات
        Route::get('/', 'index');            // لیست همه محصولات
        Route::post('/', 'store');           // ایجاد محصول جدید
        Route::put('{id}', 'update');       // به‌روزرسانی محصول
        Route::get('{id}', 'show');         // نمایش محصول
        Route::delete('{id}', 'delete');    // حذف محصول
        Route::get('mini-list', 'miniList'); // لیست کوتاه همه محصولات
    });

    // --- مراکز هزینه --- CRUD کامل
    Route::prefix('cost-centers')->controller(AccountingCostCenterController::class)->group(function () {
        Route::get('/', 'index');             // لیست مراکز هزینه
        Route::post('/', 'store');            // ایجاد مرکز هزینه جدید
        Route::get('{id}', 'show');           // نمایش مرکز هزینه
        Route::put('{id}', 'update');         // به‌روزرسانی مرکز هزینه
        Route::delete('{id}', 'destroy');     // حذف مرکز هزینه
    });

    // --- پیوند اسناد دفتر ریز حساب ---
    Route::prefix('document-links')->controller(DocumentLinkController::class)->group(function () {
        Route::post('lookup', 'lookup');   // وضعیت پیوند ردیف‌ها (فقط‌خواندنی)

        Route::get('/', 'index');
        Route::get('{id}', 'show')->whereNumber('id');

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/', 'store');
            Route::put('{id}', 'update')->whereNumber('id');
            Route::delete('{id}', 'destroy')->whereNumber('id');
        });
    });

    // --- سیستم حسابداری مرکزی (tenant = branch = office_id) ---
    Route::prefix('central-accounting/documents')->controller(CentralDocumentController::class)->group(function () {
        Route::get('/', 'index');
    });

    // پراکسی کامل روت‌های سیستم مرکزی (ledger/document/treasury/persons) — روت catch-all
    require __DIR__ . '/central_accounting.php';
});
