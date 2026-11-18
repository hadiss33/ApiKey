<?php

use App\Http\Controllers\Api\Console\AuthController;
use App\Http\Controllers\Api\Panel\V2\ShortLinkController;
use App\Http\Controllers\Api\Panel\V2\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Base API Routes
|--------------------------------------------------------------------------
|
| روت‌های پایه API اپلیکیشن.
| این روت‌ها توسط RouteServiceProvider با middleware گروه 'api' بارگذاری می‌شوند.
|
| ساختار کلی:
|  - روت‌های عمومی (بدون احراز هویت): اتصال به شعبه
|  - روت‌های short link: نیازمند JWT
|  - روت‌های احراز هویت: نیازمند تأیید دامنه و IP
|
*/

// اتصال کاربر به شعبه — عمومی، بدون نیاز به احراز هویت
Route::post('auth/connect/branch', [UserController::class, 'connectBranch']);

// روت‌های short link — نیازمند احراز هویت JWT
// توجه: کامنت 'throttle:10,1' برای فعال‌سازی rate limiting در صورت نیاز آماده است
Route::controller(ShortLinkController::class)->middleware(['domainAccess', 'ipTrust', 'authWithJwt'])->group(function () { // 'throttle:10,1'
    // ساخت short link عمومی — با rate limiting (در صورت فعال‌سازی throttle)
    Route::post('short-links/public', 'storePublic');
});

// روت‌های احراز هویت و مدیریت کاربر — نیازمند تأیید دامنه و IP
Route::group(['middleware' => ['domainAccess', 'ipTrust']], function () {
    // --- احراز هویت ---
    Route::post('auth/blocking', [AuthController::class, 'blocking']);            // مسدود کردن حساب
    Route::post('auth/sign-in', [AuthController::class, 'accessToken']);          // ورود و دریافت توکن دسترسی
    Route::post('auth/sign-out', [AuthController::class, 'signOut']);             // خروج از سیستم
    Route::post('auth/access-token', [AuthController::class, 'refreshToken']);    // تجدید توکن دسترسی
    Route::post('auth/trusted', [AuthController::class, 'accessTokenTrusted']);   // ورود با توکن دسترسی
    Route::post('auth/sign-in/otp', [AuthController::class, 'sendOtpByMobile']);    // تجدید توکن دسترسی
    Route::post('auth/sign-in/submit', [AuthController::class, 'accessTokenByOtp']);   // ورود با توکن دسترسی

    // --- مدیریت کاربر ---
    Route::post('auth/update', [UserController::class, 'update']);                // به‌روزرسانی اطلاعات کاربر
    Route::post('auth/forgot/otp', [UserController::class, 'forgotOtp']);         // ارسال OTP برای بازیابی رمز عبور
    Route::post('auth/connect/otp', [UserController::class, 'connectOtp']);       // ارسال OTP برای اتصال حساب
    Route::post('auth/connect/submit', [UserController::class, 'connectSubmit']); // تأیید نهایی اتصال حساب
    Route::post('auth/forgot/submit', [UserController::class, 'forgotSubmit']);   // تأیید نهایی بازیابی رمز عبور
});
