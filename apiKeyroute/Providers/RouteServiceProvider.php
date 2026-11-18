<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Class RouteServiceProvider
 *
 * تعریف و پیکربندی تمام روت‌های اپلیکیشن.
 *
 * این Service Provider مسئول موارد زیر است:
 *  - تعریف Rate Limiter برای APIها
 *  - ثبت گروه‌های روت با میدلوور و prefix مناسب
 *
 * ساختار روت‌ها:
 *  - api (v1 default)       : روت‌های عمومی API بدون احراز هویت دامنه/IP
 *  - api/v2 (console)       : پنل کنسول با احراز هویت دامنه و IP
 *  - api/v2/accounting      : ماژول حسابداری کنسول با احراز هویت دامنه و IP
 *  - api/b2c/v1             : API وب‌سایت B2C با احراز هویت دامنه و IP
 *  - api/reservation/v1     : API رزرواسیون با احراز هویت دامنه و رزرواسیون
 *  - web                    : روت‌های رابط کاربری وب
 *
 * @package App\Providers
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * مسیر روت "/" اپلیکیشن.
     * کاربران پس از احراز هویت به این مسیر هدایت می‌شوند.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * تعریف route model bindings، pattern filterها و سایر تنظیمات روت.
     *
     * Rate Limiter اصلی API را با سقف ۱۲۰۰ درخواست در دقیقه
     * بر اساس شناسه کاربر (در صورت احراز هویت) یا IP ثبت می‌کند.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $this->configureRateLimiting();
            return Limit::perMinute(1200)->by($request->user()?->id ?: $request->ip());
        });


        $this->routes(function () {

            // روت‌های عمومی API نسخه ۱ — بدون محدودیت دامنه و IP
            Route::middleware('api')
                ->prefix('api/v2')
                ->group(base_path('routes/api.php'));


            // روت‌های پنل کنسول نسخه ۲ — نیازمند تأیید دامنه و IP
            Route::middleware('console_api')
                ->prefix('api/v2')
                ->group(base_path('routes/api/console/console.php'));

            // روت‌های ماژول حسابداری کنسول نسخه ۲ — نیازمند تأیید دامنه و IP
            Route::middleware('console_api')
                ->prefix('api/v2/accounting')
                ->group(base_path('routes/api/console/accounting.php'));

            // روت‌های API وب‌سایت B2C نسخه ۱ — نیازمند تأیید دامنه و IP
            Route::middleware('console_api')
                ->prefix('api/b2c/v1')
                ->group(base_path('routes/api/website.php'));

            // روت‌های وبهوک بات‌های پیام‌رسان (تلگرام/بله) — بدون تأیید دامنه/IP
            Route::middleware('api')
                ->prefix('api/b2c/v1/bot')
                ->group(base_path('routes/api/bot.php'));

            // روت‌های API رزرواسیون نسخه ۱ — نیازمند تأیید دامنه و احراز هویت رزرواسیون
            Route::middleware(['api', 'domainAccess', 'reservationAuth'])
                ->prefix('api/v2/reservation/v1')
                ->group(base_path('routes/reservation.php'));
            // روت‌های رابط کاربری وب
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {

        RateLimiter::for('otp-tiered', function (Request $request) {
            $channel = $request->input('type') === 'bale' ? 'bale' : 'sms';
            $base    = getDomain() . '-' . getIp() . '-' . $channel;

            $tiers = $channel === 'bale'
                ? [[60, 10], [180, 14], [360, 16]]
                : [[60, 5],  [180, 7],  [360, 8]];

            return array_map(
                fn($t) => Limit::perMinutes($t[0], $t[1])->by($base . ':' . $t[0]),
                $tiers
            );
        });
        
        RateLimiter::for('otp-burst', function (Request $request) {
            $channel = $request->input('type') === 'bale' ? 'bale' : 'sms';

            // [سقف تعداد, بازه به دقیقه]
            [$max, $minutes] = $channel === 'bale' ? [3, 1] : [1, 2];

            return Limit::perMinutes($minutes, $max)
                ->by(getDomain() . '-' . getIp() . '-' . $channel . ':burst');
        });
    }

    /**
     * لیست مسیرهایی که از CSRF protection معاف هستند.
     * این مسیرها معمولاً endpointهای احراز هویت API هستند.
     *
     * @var array<string>
     */
    protected $except = [
        'api/auth/connect/branch',
        'api/auth/connect/otp',
        'api/auth/connect/submit',
    ];
}
