<?php

use App\Http\Controllers\Api\B2C\V1\Articles\ArticleController as V1ArticleController;
use App\Http\Controllers\Api\B2C\V1\Articles\CategoryController as V1CategoryController;
use App\Http\Controllers\Api\B2C\V1\BaseController as V1BaseController;
use App\Http\Controllers\Api\B2C\V1\CreditDebitController;
use App\Http\Controllers\Api\B2C\V1\OfficeController;
use App\Http\Controllers\Api\B2C\V1\OnlineController as V1OnlineController;
use App\Http\Controllers\Api\B2C\V1\Passengers\PassengersController;
use App\Http\Controllers\Api\B2C\V1\S3Controller as V1S3Controller;
use App\Http\Controllers\Api\B2C\V1\SitemapTreeController;
use App\Http\Controllers\Api\B2C\V1\TemporaryInformationController as V1TemporaryInformation;
use App\Http\Controllers\Api\B2C\V1\TradeController as V1TradeController;
use App\Http\Controllers\Api\B2C\V1\UserController as V1UserController;
use App\Http\Controllers\Api\Panel\V2\Sales\ReferenceController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\AccommodationController;
use App\Http\Controllers\Api\Panel\V2\Accommodation\FacilityController;
use App\Http\Controllers\Api\Panel\V2\BaseController as V2BaseController;
use App\Http\Controllers\Api\Panel\V2\CharterController;
use App\Http\Controllers\Api\Panel\V2\CreditCards;
use App\Http\Controllers\Api\Panel\V2\ShortLinkController;
use App\Http\Controllers\Api\Panel\V2\TravelRequestMessagesController;
use App\Http\Controllers\Api\Panel\V2\TravelRequestsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website API Routes (B2C v1)
|--------------------------------------------------------------------------
|
| روت‌های API وب‌سایت B2C نسخه ۱.
| این روت‌ها با middleware گروه 'console_api' (domainAccess + ipTrust) بارگذاری می‌شوند.
|
| سطوح دسترسی استفاده‌شده:
|  - بدون middleware  : endpoints کاملاً عمومی
|  - authGuest        : دسترسی مهمان (بدون لاگین)
|  - authWithJwt      : نیازمند توکن JWT معتبر
|  - recaptcha        : تأیید reCAPTCHA (در گروه یا روی خود route)
|
| نکته: ترتیب middlewareها مهم است؛ مثلاً در lock باید اول JWT و بعد recaptcha اجرا شود
| و در auth/otp باید throttle قبل از recaptcha باشد.
|
*/

// پیکربندی عمومی اپلیکیشن — بدون نیاز به احراز هویت
Route::get('config', [V1BaseController::class, 'config']);
// --- احراز هویت کاربر B2C ---
Route::controller(V1UserController::class)->middleware(['authGuest'])->group(function () {
    // ترتیب throttle قبل از recaptcha عمداً حفظ شده
    Route::post('/auth/otp', 'authOtp')->middleware(['throttle:otp-burst', 'throttle.success:otp-tiered', 'recaptcha']);
    Route::post('/auth/submit', 'authSubmit')->middleware('recaptcha'); // تأیید OTP و دریافت توکن
    Route::post('/auth/basic', 'authBasic')->middleware('recaptcha');   // ورود با نام کاربری و رمز عبور
    Route::post('/get_country', 'getCitizen');                          // دریافت لیست کشورها برای فرم ثبت‌نام
});

// --- مدیریت پروفایل و کاربران --- (نیازمند JWT)
Route::controller(V1UserController::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('/auth/resolve-identity', 'resolveIdentity')->middleware('recaptcha'); // رفع ابهام چند مسافرِ هم‌شماره با کد ملی
    Route::get('/user/passengers', 'passengersList');                                  // لیست مسافران کاربر
    Route::get('/office/users', 'usersOffice');                                        // لیست کاربران دفتر
    Route::get('/cost-center/list/form', 'getCostCenterForRequest');                   // لیست مراکز هزینه برای فرم درخواست
    Route::get('/user/profile/{id}', 'fetchUserInfo');                                 // دریافت اطلاعات پروفایل
    Route::put('/user/customer/{id}', 'updateCustomer');                               // به‌روزرسانی اطلاعات مشتری
    Route::put('/user/colleague/{id}', 'updateColleague')->middleware('recaptcha');    // به‌روزرسانی اطلاعات همکار
    Route::get('/office/team', 'officeTeam');                                          // لیست تیم دفتر
});

// --- مدیریت مسافران --- (نیازمند JWT + recaptcha روی همه route‌ها)
Route::prefix('passengers')->controller(PassengersController::class)->middleware(['authWithJwt', 'recaptcha'])->group(function () {
    Route::get('', 'index');      // لیست مسافران
    Route::put('{id}', 'update'); // به‌روزرسانی اطلاعات مسافر
});

// --- رزرو چارتر --- (نیازمند JWT)
Route::prefix('charter')->controller(CharterController::class)->middleware(['authWithJwt'])->group(function () {
    // عملیات نوشتن: نیازمند recaptcha
    Route::middleware('recaptcha')->group(function () {
        Route::post('reservation', 'insertListCharterReservation'); // ثبت رزرو چارتر
        Route::put('reservation', 'updateCharterReservation');      // به‌روزرسانی رزرو چارتر
    });

    Route::delete('reservation', 'deleteCharterReservation');   // حذف رزرو چارتر
    Route::get('reservation/{type}', 'listCharterReservation'); // لیست رزروها بر اساس نوع
    Route::get('warranty', 'listWarrantyCharter');              // لیست ضمانت‌های چارتر
});

// --- کارت‌های اعتباری --- (نیازمند JWT)
Route::controller(CreditCards::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/credit-card', 'showCreditCard'); // نمایش اطلاعات کارت اعتباری
});

// --- آپلود فایل به S3 --- (دسترسی مهمان + recaptcha)
Route::controller(V1S3Controller::class)->middleware(['authGuest', 'recaptcha'])->group(function () {
    Route::post('/media/upload/s3', 'uploadFile'); // آپلود فایل به فضای ابری S3
});

// --- داده‌های پایه --- (دسترسی مهمان)
Route::controller(V1BaseController::class)->middleware(['authGuest'])->group(function () {
    Route::get('/base/data', 'baseData');                                  // داده‌های پایه سیستم
    Route::get('/base/suggestions/items/{type}', 'suggestionsItemsIndex'); // پیشنهادات بر اساس نوع
});

Route::controller(V2BaseController::class)->middleware(['authGuest'])->group(function () {
    Route::get('/accommodations', 'accommodationListBase'); // لیست اقامتگاه‌ها
});

// --- تراکنش‌های مالی (Trade) --- (نیازمند JWT)
Route::prefix('trade')->controller(V1TradeController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('list', 'listTrade');                                        // لیست تراکنش‌ها
    // جریان واحد خرید (ReservationFacadeService) — جایگزین storeTradeBeforePay / storeTradeAfterPay
    Route::post('store', [ReferenceController::class, 'store'])->defaults('pay_required', true)->middleware('recaptcha');          // ثبت خرید و ساخت لینک پرداخت (یا خرید با کیف پول)
    Route::post('completion', [ReferenceController::class, 'store']); // تکمیل خرید بعد از پرداخت (با temporary_id)
});

// --- جستجو و عملیات آنلاین --- (دسترسی مهمان)
Route::prefix('online')->middleware(['authGuest'])->group(function () {
    Route::controller(V1OnlineController::class)->group(function () {

        // جستجو و اقامتگاه: همه recaptcha دارند
        Route::middleware('recaptcha')->group(function () {

            Route::prefix('accommodation')->group(function () {
                Route::get('/list', 'listAccommodation');                // لیست اقامتگاه‌ها
                Route::get('/get_room_type_prices', 'getRoomTypePrices'); // قیمت انواع اتاق
                Route::get('/get_details', 'getAccommodationDetails');    // جزئیات اقامتگاه
            });

            Route::post('/search/{type}', 'routeSearch'); // جستجوی آنلاین بر اساس نوع (پرواز، هتل و ...)
        });
        Route::get('accommodation/get_min_prices', [AccommodationController::class, 'getAccommodationMinPrices']); // حداقل قیمت‌ها

        // رزرو موقت — ترتیب authWithJwt بعد recaptcha عمداً روی خود route حفظ شده
        // (باید بعد از search و قبل از penalty/refund بماند تا ترتیب تطبیق route‌ها عوض نشود)
        Route::post('/{type}/lock', 'lockItemProgress')->middleware(['authWithJwt', 'recaptcha']);

        // عملیات روی آیتم انتخاب‌شده
        Route::middleware('recaptcha')->group(function () {
            Route::post('/{type}/penalty', 'penaltyItemProgress'); // محاسبه جریمه کنسلی
            Route::post('/{type}/refund', 'refundItemProgress');   // درخواست استرداد
        });

        // پرداخت و پرواز — بدون recaptcha
        Route::get('/payment/details', 'paymentDetails');                    // جزئیات پرداخت
        Route::get('/flight/class/{calculation_id}', 'getFlightClassData');  // اطلاعات کلاس پروازی
        Route::get('/payment/flight/tracking', 'trackingPaymentFlight');     // پیگیری پرداخت پرواز
    });
});

// --- گزارش مالی --- (نیازمند JWT)
Route::controller(CreditDebitController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/financial/list', 'listFinancial'); // لیست تراکنش‌های مالی
});

// --- مسافران قبلی --- (نیازمند JWT)
Route::controller(PassengersController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/passengers/previous', 'indexPassengersPrevious'); // لیست مسافران سفرهای قبلی
});

// --- امکانات اقامتگاه --- (دسترسی مهمان)
Route::controller(FacilityController::class)->middleware(['authGuest'])->group(function () {
    Route::get('facilities', 'index'); // لیست امکانات اقامتگاه‌ها
});

// --- درگاه پرداخت و تخفیف --- (دسترسی مهمان)
Route::controller(CreditDebitController::class)->middleware(['authGuest'])->group(function () {
    Route::get('/gateway/details', 'paymentGatewayDetails');                   // جزئیات درگاه پرداخت
    Route::get('/discount/submit', 'discountSubmit')->middleware('recaptcha');     // اعمال کد تخفیف
    Route::get('/discount/unsubmit', 'discountUnSubmit')->middleware('recaptcha'); // لغو کد تخفیف
});

// --- کیف پول و دفتر حساب --- (نیازمند JWT)
Route::controller(CreditDebitController::class)->middleware(['authWithJwt'])->group(function () {
    Route::get('/wallet/ballance', 'walletBalance');                          // موجودی کیف پول
    Route::post('/wallet/credit', 'walletCredit')->middleware('recaptcha');   // شارژ کیف پول
    Route::get('/ledger-account', 'ledgerAccount');                           // دفتر حساب
});

// --- مقالات و دسته‌بندی‌ها --- (عمومی، بدون middleware)
Route::get('/articles', [V1ArticleController::class, 'index']);                      // لیست مقالات
Route::get('/articles/{article}', [V1ArticleController::class, 'show']);             // نمایش مقاله
Route::get('/categories', [V1CategoryController::class, 'index']);                   // لیست دسته‌بندی‌ها
Route::get('/categories/{category}', [V1CategoryController::class, 'show']);         // نمایش دسته‌بندی
Route::post('/articles/{id}/views', [V1ArticleController::class, 'incrementViews']); // افزایش بازدید مقاله

// حداقل قیمت پرواز به ازای هر روز — از BaseController نسخه ۲
Route::get('/flights/prices/min/day', [V2BaseController::class, 'getMinPricesPerDay'])->middleware('recaptcha');

// --- درخواست‌های سفر --- (نیازمند JWT + recaptcha روی همه route‌ها)
Route::middleware(['authWithJwt', 'recaptcha'])->group(function () {
    Route::resource('travel-requests', TravelRequestsController::class); // CRUD کامل درخواست سفر

    Route::prefix('travel-requests/{travelRequestId}/messages')
        ->controller(TravelRequestMessagesController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::delete('/{id}', 'destroy');
        });
});

// --- لینک کوتاه عمومی --- (دسترسی مهمان + recaptcha)
Route::controller(ShortLinkController::class)->middleware(['authGuest', 'recaptcha'])->group(function () {
    Route::post('short-links/public', 'storePublic'); // ساخت لینک کوتاه عمومی
});

// --- اطلاعات موقت --- (دسترسی مهمان)
Route::controller(V1TemporaryInformation::class)->middleware(['authGuest'])->group(function () {
    Route::post('temporary-information/store', 'store')->middleware('recaptcha'); // ذخیره اطلاعات موقت
    Route::get('temporary-information/show', 'show');                             // دریافت اطلاعات موقت
});

// --- اطلاعات شعبه‌ها B2C --- (دسترسی مهمان)
Route::controller(OfficeController::class)->prefix('tenants')->middleware(['authGuest'])->group(function () {
    Route::get('about_us', 'aboutOffice');          // اطلاعات شعبه و تیم
    Route::get('social_media', 'socialOffice');     // اطلاعات شبکه‌های اجتماعی شعبه
    Route::get('certificates', 'officeCertificates'); // اطلاعات گواهی‌های شعبه
    Route::get('member', 'singleTeamMember');       // اطلاعات یک عضو تیم
    Route::get('domains', 'allB2CDomains');         // اطلاعات دامنه‌های شعبه
});

Route::get('/sitemap-tree', SitemapTreeController::class)->middleware(['authGuest']);
