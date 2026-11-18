<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GzipMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    /**
     * پاسخ‌های کوچک‌تر از این آستانه فشرده‌سازی نمی‌شوند؛ سربار CPU برای
     * فشرده‌سازی یک پاسخ چندبایتی بیشتر از صرفه‌جویی شبکه‌ای آن است.
     */
    private const MIN_COMPRESSIBLE_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            $content = $response->getContent();

            if (strlen($content) >= self::MIN_COMPRESSIBLE_BYTES) {
                // سطح ۶: تعادل استاندارد بین نسبت فشرده‌سازی و مصرف CPU (سطح ۹ کندترین حالت است)
                $compressed = gzencode($content, 6);

                $response->setContent($compressed);
                $response->header('Content-Encoding', 'gzip');
                $response->header('Vary', 'Accept-Encoding');
                $response->header('Content-Length', strlen($compressed));
            }
        }

        return $response;
    }
}
