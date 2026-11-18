<?php

namespace App\Http\Middleware;

use App\Facades\ApiResponse;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottleOnSuccess — مانند throttle استاندارد لاراول عمل می‌کند، با این تفاوت
 * که سقف نرخ فقط برای درخواست‌هایی که با پاسخ 200 (موفق) پایان می‌یابند شمارش
 * (hit) می‌شود؛ درخواست‌هایی که به خطا می‌خورند (422، 429 و ...) در سقف محاسبه
 * نمی‌گردند، چون خودشان نشانه‌ی تلاش ناموفق‌اند نه سوءاستفاده.
 *
 * استفاده در روت: throttle.success:otp-tiered
 * نام limiter باید از قبل با RateLimiter::for() تعریف شده باشد.
 */
class ThrottleOnSuccess
{
    public function handle(Request $request, Closure $next, string $limiterName): Response
    {
        $limits = $this->resolveLimits($limiterName, $request);

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                return $this->buildTooManyAttemptsResponse($limit);
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_OK) {
            foreach ($limits as $limit) {
                RateLimiter::hit($limit->key, $limit->decaySeconds);
            }
        }

        return $response;
    }

    /** @return Limit[] */
    private function resolveLimits(string $limiterName, Request $request): array
    {
        $limiter = RateLimiter::limiter($limiterName);

        if (! $limiter) {
            return [];
        }

        $limits = $limiter($request);

        return is_array($limits) ? $limits : [$limits];
    }

    private function buildTooManyAttemptsResponse(Limit $limit): Response
    {
        $retryAfter = RateLimiter::availableIn($limit->key);

        $response = ApiResponse::error(
            Response::HTTP_TOO_MANY_REQUESTS,
            __('middleware.too_many_requests'),
            null,
            ['retry_after' => $retryAfter]
        );

        return $response->header('Retry-After', (string) $retryAfter);
    }
}
