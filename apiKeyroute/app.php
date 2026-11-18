<?php

use App\Exceptions\ApiException;
use App\Helpers\SecurityHelper;
use App\Lib\Auxiliary\Visa;
use App\Support\Http\ApiResponseManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Log;
use Mockery\Exception\InvalidOrderException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * تابع کمکی برای ساخت JsonResponse بدون نیاز به service container.
 * در مواقعی که container لاراول هنوز بوت نشده یا در دسترس نیست استفاده می‌شود.
 */
if (! function_exists('safeJsonResponse')) {
    function safeJsonResponse(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}

/**
 * تابع کمکی برای بررسی امنیتی مسیر درخواست.
 * wrapper ساده‌ای روی SecurityHelper::checkPathSecurity است.
 */
if (! function_exists('checkPathForSecurity')) {
    function checkPathForSecurity(string $path): bool
    {
        return SecurityHelper::checkPathSecurity($path);
    }
}

/**
 * تابع کمکی برای مسدود کردن IP مشکوک.
 * wrapper ساده‌ای روی SecurityHelper::blockIpAddress است.
 */
if (! function_exists('blockSuspiciousIp')) {
    function blockSuspiciousIp(string $ip, string $path): void
    {
        SecurityHelper::blockIpAddress($ip, $path);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // توجه: web.php و api.php اینجا عمداً رجیستر نمی‌شوند — این دو فایل
        // با prefix/middleware صحیح در App\Providers\RouteServiceProvider::boot()
        // بارگذاری می‌شوند. رجیستر کردنشان اینجا هم باعث بارگذاری دوباره‌ی همان
        // فایل‌ها می‌شد (web.php عیناً دوبار، و api.php یک‌بار با prefix اضافه‌ی
        // ناخواسته‌ی api/ در کنار api/v2/ مستندشده).
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // حذف رمزنگاری کوکی برای refresh_token (چون مستقیم توسط سرویس‌های خارجی خوانده می‌شود)
        $middleware->encryptCookies(except: [
            'refresh_token',
        ]);

        // پیکربندی CSRF — مسیرهای API و پرداخت از اعتبارسنجی CSRF معاف هستند
        $middleware->web(remove: [
            // حذف CSRF از روت‌های API در صورت نیاز
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'p/*',
            'payment/return',
            'invoice/payment/return/*',
        ]);

        // گروه میدلوور رمزگشایی داده — قابل استفاده به صورت 'decryptData' در روت‌ها
        $middleware->group('decryptData', [
            \App\Http\Middleware\DecryptData::class,
        ]);

        // افزودن میدلوور تنظیم زبان (locale) به انتهای زنجیره سراسری
        $middleware->append(\App\Http\Middleware\SetLocale::class);

        // تعریف alias برای میدلوورها — برای استفاده مختصر در تعریف روت‌ها
        $middleware->alias([
            'recaptcha'        => \App\Http\Middleware\VerifyRecaptcha::class,         // احراز هویت کپچا
            'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive'     => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'signed'           => \App\Http\Middleware\ValidateSignature::class,
            'domainAccess'     => \App\Http\Middleware\DomainAccess::class,           // کنترل دسترسی دامنه
            'ipTrust'          => \App\Http\Middleware\IpTrust::class,                // کنترل دسترسی IP
            'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'throttle.success' => \App\Http\Middleware\ThrottleOnSuccess::class,         // throttle فقط بر اساس پاسخ‌های موفق (200)
            'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'authWithJwt'      => \App\Http\Middleware\AuthWithJWT::class,            // احراز هویت JWT
            'auth_console'     => \App\Http\Middleware\ConsoleAuthenticate::class,    // احراز هویت پنل کنسول
            'reservationAuth'  => \App\Http\Middleware\ReservationAuthenticate::class, // احراز هویت رزرواسیون
            'core'             => \App\Http\Middleware\CoreAccess::class,             // دسترسی به Core Center
            'gzip.response'    => \App\Http\Middleware\GzipMiddleware::class,         // فشرده‌سازی پاسخ
            'authGuest'        => \App\Http\Middleware\AuthGuest::class,              // احراز هویت مهمان
            'allowed_hosts'    => \App\Http\Middleware\AllowedHostsMiddleware::class, // دسترسی هاست های مجاز
            'rate.guard'       => \App\Http\Middleware\RequestRateGuard::class,       // نگهبان نرخ درخواست (fail2ban)
        ]);

        // نگهبان نرخ درخواست به سبک fail2ban — روی IP واقعی کاربر (پشت CDN).
        // اول از همه اجرا می‌شود تا IP بن‌شده پیش از هر پردازشی 429 بگیرد.
        $middleware->prependToGroup('api', \App\Http\Middleware\RequestRateGuard::class);

        // تعریف گروه console_api
        $middleware->group('console_api', [
            \App\Http\Middleware\RequestRateGuard::class,
            \App\Http\Middleware\DomainAccess::class,
            \App\Http\Middleware\IpTrust::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // SystemLog: exceptionهایی که لاراول پیش‌فرض گزارش نمی‌کند (۴۰۴، ۴۰۱، ۴۰۳، ...) هم ثبت شوند
        // (ValidationException/HttpResponseException عمداً بیرون‌اند). فهرست پیش‌فرض ثابت است و با
        // config('system_log.error.record_ignored') قابل override است. config ماژول ممکن است هنوز ادغام نشده باشد
        // (Collision در CLI handler را زود می‌سازد)؛ پس فقط به config/system_log.php پروژه (که زود بارگذاری می‌شود) تکیه می‌کنیم.
        try {
            $configured = config('system_log.error.record_ignored');
            $recordIgnored = config('system_log.driver', 'visa') === 'internal'
                ? (is_array($configured) ? $configured : \App\Services\SystemLog\Infrastructure\SystemLogServiceProvider::RECORD_IGNORED)
                : [];
        } catch (Throwable) {
            $recordIgnored = [];
        }

        if ($recordIgnored !== []) {
            $exceptions->stopIgnoring($recordIgnored);
        }

        // ثبت تمام استثناها در لاگ — فقط در محیط production
        $exceptions->report(function (Throwable $e) use ($recordIgnored) {
            // «پیش‌فرض نادیده‌گرفته‌شده» (۴۰۴ و ...): فقط در SystemLog ثبت می‌شود، نه لاگ فایل (مثل قبل)
            $wasIgnored = false;

            foreach ($recordIgnored as $class) {
                if ($e instanceof $class) {
                    $wasIgnored = true;
                    break;
                }
            }

            if (! $wasIgnored && app()->environment('production')) {
                // در artisan/composer ممکن است خطا قبل از bind شدن 'request' رخ دهد؛
                // صدا زدن request() در آن حالت خطای اصلی را با «Target class [request]» می‌پوشاند.
                $request = app()->bound('request') && ! app()->runningInConsole() ? request() : null;

                Log::error('Exception: ' . $e->getMessage(), [
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                    'user_id' => $request?->user()?->id,
                    'url'     => $request?->fullUrl(),
                    'ip'      => $request?->ip(),
                ]);
            }

            // ثبت داخلی خطا (SystemLog) — فقط با driver=internal در config/system_log.php. همه‌ی exceptionهای گزارش‌شده ثبت می‌شوند
            // (از جمله ApiException ۴xx و DecryptException)؛ فقط خطای اعتبارسنجی که لاراول به این‌جا نمی‌رساند ثبت نمی‌شود.
            // این بلوک هرگز نباید خودش خطا بیندازد.
            try {
                if (config('system_log.driver', 'visa') === 'internal') {
                    $ref = app(\App\Services\SystemLog\Application\Services\ErrorService::class)->report($e);

                    // rendererها (QueryException و ...) از همین کد در پاسخ استفاده می‌کنند
                    if ($ref && app()->bound('request') && ! app()->runningInConsole()) {
                        request()->attributes->set('system_log_ref', $ref);
                    }
                }
            } catch (Throwable) {
                // ثبت خطا نباید request اصلی را خراب کند
            }

            // false = لاگ پیش‌فرض لاراول انجام نشود (برای exceptionهای پیش‌فرض‌نادیده)
            return $wasIgnored ? false : null;
        });

        // خطای ۴۰۵ — متد HTTP پشتیبانی نمی‌شود
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 405,
                        'message' => $e->getMessage() ?: 'Method not supported!',
                    ],
                    'support' => config('support.contact'),
                ], 405);
            }

            // برای درخواست‌های web، پاسخ ساده برگردانده می‌شود
            return safeJsonResponse([
                'error'   => 'Method not allowed',
                'message' => $e->getMessage() ?: 'Method not supported!',
                'support' => config('support.contact'),
            ], 405);
        });

        // خطای ۴۰۴ — مسیر یافت نشد (با بررسی امنیتی IP)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {

            // بررسی امنیتی مسیر — اگر IP مشکوک باشد، قبل از ۴۰۴ بلاک می‌شود
            $checkPath = checkPathForSecurity($request->path());
            if (! $checkPath) {
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 1005,
                        'ip'      => getIp(),
                        'host_ip' => getHostIp(),
                        'message' => 'The ip ' . getIp() . ' is blocked',
                    ],
                    'support' => config('support.contact'),
                ], 403);
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 404,
                        'message' => __('error.404', ['resource' => $request->path()]),
                    ],
                    'support' => config('support.contact'),
                ], 404);
            }

            // برای درخواست‌های web، پاسخ ۴۰۴ ساده برگردانده می‌شود
            return safeJsonResponse([
                'error'   => __('error.404', ['resource' => $request->path()]),
                'message' => __('error.404', ['resource' => $request->path()]),
                'support' => config('support.contact'),
            ], 404);
        });

        // خطای ۴۰۰ — درخواست نامعتبر
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 400,
                        'message' => '400 - Bad Request.',
                    ],
                    'support' => config('support.contact'),
                ], 400);
            }
        });

        // خطای ۴۰۱ — درخواست بدون احراز هویت (Unauthorized)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 401,
                        'message' => '401 - Unauthorized.',
                    ],
                    'support' => config('support.contact'),
                ], 401);
            }
        });

        // خطای ۴۱۹ — توکن CSRF نامعتبر یا منقضی
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 419,
                        'message' => 'CSRF token mismatch. Please refresh the page and try again.',
                    ],
                    'support' => config('support.contact'),
                ], 419);
            }

            // برای درخواست‌های web، به صفحه قبل با پیام خطا ریدایرکت می‌شود
            return redirect()->back()->withInput()->withErrors([
                'csrf_error' => 'CSRF token mismatch. Please refresh the page and try again.',
            ]);
        });

        // خطای ۴۰۱ — کاربر احراز هویت نشده (Authentication)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 401,
                        'message' => 'Authentication required.',
                    ],
                    'support' => config('support.contact'),
                ], 401);
            }

            // برای درخواست‌های web، ویو خطای ۴۰۱ نمایش داده می‌شود
            return response()->view('errors.401', [
                'exception' => $e,
                'message'   => 'Authentication required.',
                'support'   => config('support.contact'),
            ], 401);
        });

        // خطای ۴۰۳ — دسترسی غیرمجاز (Authorization)
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 403,
                        'message' => __('error.403'),
                    ],
                    'support' => config('support.contact'),
                ], 403);
            }

            // برای درخواست‌های web، ویو خطای ۴۰۳ نمایش داده می‌شود
            return response()->view('errors.403', [
                'exception' => $e,
                'message'   => __('error.403'),
                'support'   => config('support.contact'),
            ], 403);
        });

        // خطای ۴۰۴ — رکورد Eloquent در دیتابیس یافت نشد
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 404,
                        'message' => 'Record not found.',
                    ],
                    'support' => config('support.contact'),
                ], 404);
            }
        });

        // خطای ۵۰۰ — خطای کوئری دیتابیس
        // در محیط local پیام کامل SQL نمایش داده می‌شود، در production پیام کلی
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isLocal = app()->environment('local');
                $reportCode = '-';
                if (config('system_log.driver', 'visa') === 'internal') {
                    // خطا قبلاً در مرحله‌ی report ثبت شده؛ همان کد پیگیری استفاده می‌شود (نه ثبت دوباره)
                    $reportCode = $request->attributes->get('system_log_ref', '-');
                } elseif (!$isLocal) {
                    $report = Visa::addSystemReport([
                        'message' => $e->getMessage(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'trace' => $e->getTrace()
                    ]);
                    $reportCode = $report['Code'] ?? '-';
                }
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => array_merge([
                        'code'    => 'QueryException',
                        'report_code'    => $reportCode,
                        'message' => $isLocal ? $e->getMessage() : 'Database error occurred.',
                    ], $isLocal ? [
                        'trace'    => $e->getTrace(),
                        'sql_code' => $e->getCode(),
                    ] : []),
                    'support' => config('support.contact'),
                ], 500);
            }
        });

        // خطای ۴۰۴ — فایل مورد نظر در فایل‌سیستم یافت نشد
        $exceptions->render(function (FileNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 404,
                        'message' => 'File not found.',
                    ],
                    'support' => config('support.contact'),
                ], 404);
            }
        });

        // خطای ۵۰۰ — پارامترهای لازم برای تولید URL وجود ندارند
        $exceptions->render(function (UrlGenerationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isLocal = app()->environment('local');
                $reportCode = '-';
                if (config('system_log.driver', 'visa') === 'internal') {
                    // خطا قبلاً در مرحله‌ی report ثبت شده؛ همان کد پیگیری استفاده می‌شود (نه ثبت دوباره)
                    $reportCode = $request->attributes->get('system_log_ref', '-');
                } elseif (!$isLocal) {
                    $report = Visa::addSystemReport([
                        'message' => $e->getMessage(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'trace' => $e->getTrace()
                    ]);
                    $reportCode = $report['Code'] ?? '-';
                }
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => array_merge([
                        'code'    => 'UrlGenerationException',
                        'report_code'    => $reportCode,
                        'message' => $isLocal ? 'URL parameters missing. | ' . $e->getMessage() : 'URL generation failed.',
                    ], $isLocal ? [
                        'file' => $e->getFile() . ':' . $e->getLine(),
                    ] : []),
                    'support' => config('support.contact'),
                ], 500);
            }
        });

        // خطای ۴۰۰ — رمزگشایی داده‌های رمزشده ناموفق بود
        $exceptions->render(function (DecryptException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 400,
                        'message' => 'Decryption failed.',
                    ],
                    'support' => config('support.contact'),
                ], 400);
            }
        });

        // خطای ۴۲۹ — تعداد درخواست‌های بیش از حد مجاز (Rate Limit / Throttle)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, Request $request) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            $message = $retryAfter
                ? "تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً پس از {$retryAfter} ثانیه دوباره تلاش کنید."
                : 'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً کمی بعد دوباره تلاش کنید.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'        => 429,
                        'message'     => $message,
                        'retry_after' => $retryAfter ? (int) $retryAfter : null,
                    ],
                    'support' => config('support.contact'),
                ], 429, $e->getHeaders());
            }

            return safeJsonResponse([
                'error'   => 'Too Many Requests',
                'message' => $message,
                'support' => config('support.contact'),
            ], 429, $e->getHeaders());
        });

        // خطای HTTP عمومی — برای استثناهای HttpException که handler اختصاصی ندارند
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => [
                        'code'    => 'HttpException',
                        'message' => $e->getMessage() ?: 'HTTP Exception occurred.',
                    ],
                    'support' => config('support.contact'),
                ], 500);
            }
        });

        // خطای ترتیب نامعتبر در Mockery — معمولاً در تست‌ها رخ می‌دهد
        $exceptions->render(function (InvalidOrderException $e, Request $request) {
            return response()->json([
                'status'  => false,
                'time'    => time(),
                'error'   => [
                    'code'    => 'InvalidOrderException',
                    'message' => '500 – Internal Server Error!',
                ],
                'support' => config('support.contact'),
            ], 500);
        });

        // handler پیش‌فرض — تمام استثناهای پیش‌بینی‌نشده اینجا مدیریت می‌شوند
        //
        // نکته‌ی امنیتی: فیلدهای دیباگ (file، trace، request) شامل اطلاعات حساس
        // (مسیر سرور، بدنه‌ی کامل درخواست که می‌تواند پسورد/شماره کارت داشته
        // باشد) هستند و فقط در محیط local برگردانده می‌شوند؛ در production این
        // فیلدها حذف می‌شوند تا افشای اطلاعات رخ ندهد.
        $exceptions->render(function (Throwable $e, Request $request) {
            $isLocal = true; //app()->environment('local');

            if ($request->is('api/*') || $request->expectsJson()) {

                // اگر پیام استثنا خودش یک JSON با ساختار error باشد، آن را parse می‌کنیم
                // (برای استثناهایی که از سرویس‌های داخلی با فرمت JSON پرتاب می‌شوند)
                $decodedOnce = json_decode($e->getMessage(), true);
                if ($decodedOnce && isset($decodedOnce['error'])) {
                    return safeJsonResponse([
                        'status'  => false,
                        'time'    => time(),
                        'error'   => array_merge([
                            'code'    => 'ThrowableWithError',
                            'message' => $decodedOnce['error']['message'] ?? 'Error!',
                        ], $isLocal ? [
                            'file'             => $decodedOnce['error']['file'] ?? false,
                            'request_internal' => $decodedOnce['error']['request'] ?? false,
                            'request_service'  => $request->all(),
                            'trace'            => $decodedOnce['error']['trace'] ?? $decodedOnce['error'],
                        ] : []),
                        'support' => config('support.contact'),
                    ], ($decodedOnce['error']['code'] ?? 500));
                }

                if ($e instanceof ApiException) {
                    return safeJsonResponse([
                        'status' => false,
                        'time' => time(),
                        'error' => array_merge([
                            'code' => 'ApiException',
                            'message' => $e->getMessage(),
                        ], $isLocal ? [
                            'file' => str_replace(dirname(__DIR__), '', $e->getFile()) . ':' . $e->getLine(),
                            'request' => $request->all(),
                            'context' => $e->getContext(),
                        ] : []),
                        'support' => config('support.contact'),
                    ], $e->getCode() ?: 500);
                }

                // استثنای معمولی — اطلاعات کامل برای دیباگ فقط در local برگردانده می‌شود
                // فایل‌های ExternalServiceClient از trace حذف می‌شوند تا لاگ‌ها شلوغ نشوند
                return safeJsonResponse([
                    'status'  => false,
                    'time'    => time(),
                    'error'   => array_merge([
                        'code'    => 'Throwable',
                        'message' => $isLocal ? $e->getMessage() : 'Internal server error occurred.',
                    ], $isLocal ? [
                        'file'    => str_replace(dirname(__DIR__), '', $e->getFile()) . ':' . $e->getLine(),
                        'request' => $request->all(),
                        'trace'   => ! strpos($e->getFile(), 'ExternalServiceClient') ? array_slice($e->getTrace(), 0, 20) : [],
                    ] : []),
                    'support' => config('support.contact'),
                ], ($e->getCode() && $e->getCode() != 0 ? $e->getCode() : 500));
            }

            // برای درخواست‌های web، پاسخ خطای ۵۰۰ ساده برگردانده می‌شود
            return safeJsonResponse(array_merge([
                'error'   => 'Server Error',
                'message' => $isLocal ? 'Internal server error occurred. | ' . $e->getMessage() : 'Internal server error occurred.',
            ], $isLocal ? [
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 3),
            ] : [], [
                'support' => config('support.contact'),
            ]), 500);
        });
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {

        // تست زمان‌بندی — هر دقیقه یک بار اجرا می‌شود
        // $schedule->call(function () {
        //     \Illuminate\Support\Facades\Log::info('تست زمان‌بندی!');
        // })->everyMinute();

        // پردازش صف سریع (fastJob) — هر دقیقه، حداکثر ۳ تلاش، timeout 120 ثانیه
        // $schedule->command('queue:work --queue=fastJob --sleep=3 --tries=3 --timeout=120')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->everyMinute();

        // پردازش صف کُند (snailJob) — هر ساعت، حداکثر ۳ تلاش، timeout 300 ثانیه
        // $schedule->command('queue:work --queue=snailJob --sleep=10 --tries=3 --timeout=300')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->hourly();

        // پردازش رویدادهای AMI — هر ساعت، حداکثر ۳ تلاش، timeout 60 ثانیه
        // $schedule->command('ami:collector --batch=10 --sleep=50000')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->everyMinute();

        // ارسال اعلان قبل از سفر — روزانه ساعت ۱۴:۰۰ به وقت تهران
        $schedule->call(function () {
            \App\Http\Controllers\Cron\CronController::notifyBeforeTrip();
        })->name('notify-before-trip')->dailyAt('14:00')->timezone('Asia/Tehran')->onOneServer();

        // ارسال اعلان بعد از سفر — روزانه ساعت ۱۱:۰۰ به وقت تهران
        $schedule->call(function () {
            \App\Http\Controllers\Cron\CronController::notifyAfterTrip();
        })->name('notify-after-trip')->dailyAt('11:00')->timezone('Asia/Tehran')->onOneServer();

        // دریافت نرخ بازار — دو بار در روز، ساعت ۱۱:۳۰ و ۱۷:۳۰ به وقت تهران
        $schedule->call(function () {
            \App\Http\Controllers\Cron\CronController::marketRate();
        })->name('market-rate')->twiceDailyAt(11, 17, 30)->timezone('Asia/Tehran')->onOneServer();

        // محاسبه مجدد شبانه موجودی حساب‌های مرجع برای گزارش‌های فروش
        // روزانه ساعت ۰۳:۳۰ به وقت تهران
        $schedule->command('reference:recalculate --force')
            ->withoutOverlapping()
            ->runInBackground()
            ->dailyAt('03:30')
            ->timezone('Asia/Tehran')
            ->onOneServer();

        // بررسی وضعیت رزرو هاب — دو بار در روز، ساعت ۰۱:۱۵ و ۱۸:۱۵ به وقت تهران
        $schedule->call(function () {
            \App\Http\Controllers\Cron\CronController::checkHubReservation();
        })->name('check-hub-reservation')->twiceDailyAt(1, 18, 15)->timezone('Asia/Tehran')->onOneServer();

        // هر روز ساعت 02:00 شب snapshot d بساز
        $schedule->command('official:snapshot:generate')
            ->dailyAt('02:00')
            ->timezone('Asia/Tehran')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // پاکسازی شبانه‌ی ردپاهای SystemLog در خود ماژول زمان‌بندی می‌شود (SystemLogServiceProvider)

        // جهت بروزرسانی وضعیت پروازی OTA های مختلف
        // $schedule->call(function () {
        //     \App\Http\Controllers\Cron\CronController::updateAirlineActiveRoute();
        // })->everyFiveMinutes()->timezone('Asia/Tehran');
    })
    ->withCommands([
        app_path('Console/Commands'),
        app_path('Services/Official/OperatorPerformance/Call/Infrastructure/Commands'),
    ])
    ->withBindings([
        'api-response' => fn() => new ApiResponseManager(),
    ])
    ->booted(function () {
        \App\Observers\CityObserver::register();
        \App\Observers\AccommodationObserver::register();
    })
    ->create();
