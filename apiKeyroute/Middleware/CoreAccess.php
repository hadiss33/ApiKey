<?php

namespace App\Http\Middleware;

use App\Lib\Helpers\Functions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CoreAccess
 *
 * میدلوور کنترل دسترسی به بخش Core Center.
 *
 * این میدلوور بررسی می‌کند که آیا اپراتور احراز هویت‌شده مجاز به دسترسی
 * به Core Center است یا خیر. این دسترسی فقط برای اپراتورهایی مجاز است
 * که مقدار branch آن‌ها برابر '[0]' باشد (شعبه مرکزی / دسترسی ویژه).
 *
 * پیش‌نیاز: این میدلوور باید بعد از AuthWithJWT در زنجیره اجرا شود،
 * چون به آبجکت 'operator' تزریق‌شده توسط آن میدلوور وابسته است.
 *
 * @package App\Http\Middleware
 */
class CoreAccess
{
    /**
     * پردازش درخواست ورودی.
     *
     * مقدار branch اپراتور جاری را بررسی می‌کند:
     *  - اگر برابر '[0]' بود: درخواست به میدلوور یا کنترلر بعدی پاس داده می‌شود.
     *  - در غیر این صورت: پاسخ خطای JSON با کد ۴۰۰ و کد خطای ۵۰۰۵ برگردانده می‌شود.
     *
     * @param  \Illuminate\Http\Request                                                          $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $goal = null, ?string $subgoal = null): Response
    {
        // بررسی اینکه آیا اپراتور به شعبه مرکزی (branch = '[0]') تعلق دارد
        // یا (در صورت تعریف پارامتر، مثل core:trade,hotel_update) دسترسی مشخص‌شده را دارد
        $operator = $request->attributes->get('operator');
        $hasAccess = $operator && (
            $operator->branch == '[0]'
            || ($goal && $subgoal
                && in_array($request->attributes->get('group'), ['base', 'trusted'], true)
                && Functions::getAccessUser($goal, $subgoal, $operator->access ?? null))
        );
        if ($hasAccess) {
            return $next($request);
        } else {
            // اپراتور دسترسی ویژه ندارد؛ برگرداندن خطای دسترسی
            return response()->json([
                "error" => [
                    "code"    => 5005,
                    "message" => "Access to the [core center] requires special access."
                ],
                "meta" => [
                    "timestamp"    => time(),
                    "support" => config('support.contact')
                ],
            ], 400);
        }
    }
}
