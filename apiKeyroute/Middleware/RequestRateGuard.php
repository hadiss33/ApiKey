<?php

namespace App\Http\Middleware;

use App\Support\Security\Fail2Ban;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequestRateGuard — نگهبان نرخ درخواست به سبک fail2ban.
 *
 * هر IP در یک بازه‌ی لغزان شمرده می‌شود؛ اگر تعداد درخواست‌ها از آستانه‌ی
 * «عرفی» عبور کند، آن IP برای چند دقیقه بن می‌شود و تا پایان مدت بن،
 * پاسخ 429 (Too Many Requests) دریافت می‌کند. تخلف مکرر مدت بن را نمایی
 * افزایش می‌دهد.
 *
 * پیاده‌سازی روی Redis است (ZSET برای پنجره‌ی لغزان). اگر Redis در دسترس
 * نباشد، میدلوور «fail-open» عمل می‌کند: خطا لاگ می‌شود ولی درخواست عبور
 * می‌کند تا یک اختلال زیرساختی باعث قطعی کل سرویس نشود.
 *
 * استفاده در روت‌ها:
 *   Route::middleware('rate.guard')->group(...);              // آستانه‌ی پیش‌فرض config
 *   Route::middleware('rate.guard:120,60,900')->group(...);   // max=120 در window=60s، بن=900s
 *
 * پارامترها به ترتیب: maxRequests , window(seconds) , banSeconds
 *
 * مدیریت بن‌ها از خط فرمان (رفع سریع، مشاهده، کامنت‌گذاری):
 *   php artisan fail2ban list
 *   php artisan fail2ban status --ip=1.2.3.4
 *   php artisan fail2ban unban --ip=1.2.3.4
 *   php artisan fail2ban note  --ip=1.2.3.4 --reason="false-positive؛ جاب دسته‌ای شریک"
 *   php artisan fail2ban ban   --ip=1.2.3.4 --minutes=30 --reason="..."
 *
 * وضعیت بن روی Redis نگه‌داری می‌شود و از App\Support\Security\Fail2Ban خوانده/نوشته می‌شود.
 */
class RequestRateGuard
{
    public function handle(Request $request, Closure $next, ?string $max = null, ?string $window = null, ?string $ban = null): Response
    {
        if (! config('fail2ban.enabled', true)) {
            return $next($request);
        }

        // ---- پارامترهای مؤثر (override روت روی مقدار config) --------------------
        $maxRequests = (int) ($max ?? config('fail2ban.max_requests', 500));
        $windowSecs  = (int) ($window ?? config('fail2ban.window', 60));
        $banSecs     = (int) ($ban ?? config('fail2ban.ban_seconds', 600));

        // ---- تشخیص IP واقعی کاربر (پشت CDN) ---------------------------------
        $ip = getIp();

        // IP نامعتبر یا لوکال/خصوصی → معاف
        if (! filter_var($ip, FILTER_VALIDATE_IP) || $this->isPrivateIp($ip)) {
            return $next($request);
        }

        // لیست سفید یا مسیر معاف → معاف
        if ($this->isWhitelisted($ip) || $this->isExceptPath($request)) {
            return $next($request);
        }

        try {
            // ۱) اگر همین حالا بن است → 429
            if (($ttl = Fail2Ban::ttl($ip)) > 0) {
                return $this->tooManyRequests($ttl);
            }

            // ۲) ثبت این درخواست در پنجره‌ی لغزان و گرفتن تعداد فعلی
            $hits = Fail2Ban::recordHit($ip, $windowSecs);

            // ۳) عبور از آستانه → صدور بن (با تشدید نمایی)
            if ($hits > $maxRequests) {
                $reason = sprintf('flood: %d req / %ds on %s', $hits, $windowSecs, $request->path());
                $result = Fail2Ban::escalate($ip, $banSecs, $reason);
                $this->logBan($request, $ip, $hits, $maxRequests, $windowSecs, $result['seconds'], $result['strike']);

                return $this->tooManyRequests($result['seconds']);
            }
        } catch (\Throwable $e) {
            // fail-open: اختلال Redis نباید سرویس را بخواباند
            Log::channel(config('fail2ban.log_channel'))
                ->error('RequestRateGuard skipped due to backend error', [
                    'ip'    => $ip,
                    'error' => $e->getMessage(),
                ]);
        }

        return $next($request);
    }

    // ======================================================================
    //  کمکی‌ها
    // ======================================================================

    private function tooManyRequests(int $retryAfter): Response
    {
        return response()->json([
            'status'  => false,
            'time'    => time(),
            'error'   => [
                'code'        => 429,
                'message'     => __('middleware.too_many_requests') ?: 'Too many requests. You are temporarily blocked, please try again later.',
                'retry_after' => $retryAfter,
            ],
            'support' => config('support.contact'),
        ], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    private function isExceptPath(Request $request): bool
    {
        foreach ((array) config('fail2ban.except_paths', []) as $pattern) {
            if ($request->is($pattern) || fnmatch($pattern, $request->path())) {
                return true;
            }
        }

        return false;
    }

    private function isWhitelisted(string $ip): bool
    {
        foreach ((array) config('fail2ban.whitelist', []) as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($entry === $ip) {
                return true;
            }
        }

        return false;
    }

    /** IPهای لوکال/خصوصی (loopback, RFC1918, link-local, ULA) هرگز بن نمی‌شوند. */
    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /** بررسی تعلق یک IPv4 به رنج CIDR (IPv6 پشتیبانی نمی‌شود، ساده رد می‌شود). */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');
        $bits = (int) $bits;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function logBan(Request $request, string $ip, int $hits, int $max, int $window, int $banSecs, int $strike): void
    {
        Log::channel(config('fail2ban.log_channel'))->warning('Fail2Ban: IP temporarily blocked for request flooding', [
            'ip'          => $ip,
            'host_ip'     => getHostIp(),
            'path'        => $request->path(),
            'method'      => $request->method(),
            'hits'        => $hits,
            'threshold'   => $max,
            'window_secs' => $window,
            'ban_secs'    => $banSecs,
            'strike'      => $strike,
            'user_agent'  => $request->header('User-Agent') ?? 'Unknown',
            'timestamp'   => now()->toISOString(),
        ]);
    }
}
