<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ConsoleAuthenticate
 *
 * میدلوور احراز هویت پنل کنسول.
 *
 * بررسی می‌کند که آیا کاربر قبلاً از طریق پنل کنسول احراز هویت کرده است یا نه.
 * وضعیت احراز هویت از طریق مقدار کوکی رمزنگاری‌شده‌ی 'authenticated' (که توسط
 * EncryptCookies امضا/رمز می‌شود) تشخیص داده می‌شود — نه صرفاً وجود کوکی؛
 * چون یک کوکی خام و بدون امضا به‌راحتی توسط کلاینت قابل جعل است.
 *
 * در صورت نبود یا نامعتبر بودن کوکی:
 *  - آدرس درخواست فعلی در کوکی 'intended' ذخیره می‌شود (برای ریدایرکت بعد از لاگین)
 *  - کاربر به صفحه لاگین کنسول هدایت می‌شود
 *
 * @package App\Http\Middleware
 */
class ConsoleAuthenticate
{
    /**
     * پردازش درخواست ورودی.
     *
     * اگر کوکی 'authenticated' مقدار معتبر ('granted') نداشته باشد:
     *  1. آدرس فعلی درخواست در کوکی 'intended' با عمر ۳ دقیقه ذخیره می‌شود
     *  2. کاربر به روت 'console.auth' ریدایرکت می‌شود
     *
     * در غیر این صورت، درخواست به میدلوور یا کنترلر بعدی پاس داده می‌شود.
     *
     * @param  \Illuminate\Http\Request                                                          $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // کوکی از طریق EncryptCookies رمزگشایی/تایید می‌شود؛ مقدار خام و جعلی
        // توسط کلاینت رد می‌شود چون امضای معتبر ندارد.
        if ($request->cookie('authenticated') !== 'granted') {
            // درخواست‌های fetch/AJAX (مثل console/run) یک 401 JSON می‌گیرند
            // تا کلاینت بتواند به‌جای دنبال‌کردن ریدایرکت به صفحه‌ی HTML لاگین،
            // کاربر را با یک پیام مشخص به صفحه‌ی ورود برگرداند.
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'lines'  => ['Session expired. Please sign in again.'],
                    'next'   => ['type' => 'done'],
                    'redirect' => '/console/auth',
                ], 401);
            }

            // ذخیره آدرس فعلی برای ریدایرکت پس از ورود موفق (عمر: ۳ دقیقه)
            cookie()->queue(cookie('intended', $request->url(), 3, '/', null, null, true, false, 'Strict'));
            return redirect()->route('console.auth');
        }

        return $next($request);
    }
}
