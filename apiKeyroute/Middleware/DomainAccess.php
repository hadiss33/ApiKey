<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DomainAccess
 *
 * میدلوور کنترل دسترسی بر اساس دامنه درخواست.
 *
 * این میدلوور بررسی می‌کند که آیا دامنه‌ای که درخواست از طریق آن ارسال شده،
 * در لیست دامنه‌های مجاز سیستم ثبت شده است یا نه.
 * اطلاعات دامنه از هدر سفارشی (از طریق تابع getDomain) خوانده می‌شود.
 *
 * جریان اجرا:
 *  1. خواندن هدر دامنه از درخواست
 *  2. استخراج دامنه پایه (حذف پورت و www)
 *  3. جستجوی دامنه در جدول authenticator_trust (با کش ۱۰ دقیقه‌ای)
 *  4. در صورت وجود دامنه demo، سوئیچ به دیتابیس و Redis مخصوص demo
 *  5. تزریق branch_id به Request و ادامه زنجیره
 *
 * در صورت موفقیت، مقدار زیر به Request اضافه می‌شود:
 *  - 'branch' : شناسه شعبه متناظر با دامنه (از جدول authenticator_trust)
 *
 * @package App\Http\Middleware
 */
class DomainAccess
{
    /**
     * پردازش درخواست ورودی.
     *
     * دامنه درخواست را اعتبارسنجی کرده و در صورت مجاز بودن،
     * شناسه شعبه را به Request تزریق می‌کند.
     *
     * @param  \Illuminate\Http\Request                                                          $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  mixed                                                                             ...$methods  پارامترهای اضافی میدلوور (در صورت نیاز)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$methods): Response
    {
        // خواندن هدر دامنه از درخواست
        $domainHeader = getDomain();
        if (empty($domainHeader)) {
            return $this->createErrorResponse(1003, [
                "host"    => $request->getHost(),
                "message" => "The domain header is required to access the system"
            ]);
        }

        // استخراج دامنه پایه (بدون پورت و بدون www)
        $baseDomain = $this->extractBaseDomain($domainHeader);

        // واکشی اطلاعات دسترسی دامنه با کش ۶۰۰ ثانیه‌ای (۱۰ دقیقه)
        // برای جلوگیری از کوئری مکرر به دیتابیس در هر درخواست
        $cacheKey = "domain_access_{$baseDomain}";
        $accessData = Cache::remember($cacheKey, 600, function () use ($baseDomain) {
            // جستجوی دامنه در جدول دامنه‌های مجاز
            $getTrustDomain = DB::table('authenticator_trust')
                ->select('target')
                ->where([
                    ['type', '=', 'branch'],
                    ['object_type', '=', 'domain'],
                    ['object', '=', $baseDomain],
                    ['status', '=', 1]
                ])
                ->first();

            if (is_null($getTrustDomain?->target)) {
                return [
                    "host"    => request()->getHost(),
                    "message" => __('middleware.domain_not_have_branch')
                ];
            }

            return [
                'domain'    => $baseDomain,
                // اگر دامنه یافت نشد، branch_id برابر null خواهد بود
                'branch_id' => $getTrustDomain ? $getTrustDomain->target : null
            ];
        });
        // دامنه در لیست مجاز نیست
        if (!isset($accessData['branch_id']) || (isset($accessData['branch_id']) && !$accessData['branch_id'])) {
            return $this->createErrorResponse(1004, [
                "domain"  => $domainHeader,
                "host"    => $request->getHost(),
                "message" => __('middleware.not_allow_to_system', ['value' => $domainHeader])
            ]);
        }

        // سوئیچ به دیتابیس و Redis مخصوص محیط demo
        // دامنه‌هایی که شامل 'demo.' یا 'aipl' هستند به اتصال جداگانه هدایت می‌شوند
        if (str_contains($baseDomain, 'demo.') || str_contains($baseDomain, 'aipl')) {
            Config::set('database.default', 'mysql_demo');
            Config::set('database.redis.default.database', 5);
            // به‌جای DB::reconnect() (که فوراً یک اتصال جدید باز می‌کند)، فقط
            // کانکشن قبلی (در صورت وجود) را دور می‌ریزیم؛ اتصال به mysql_demo
            // به‌صورت lazy توسط اولین کوئری واقعی برقرار می‌شود
            DB::purge('mysql');
        }

        if (!$accessData['branch_id']) {
            return $this->createErrorResponse(1003, [
                "host"    => $request->getHost(),
                "message" => $request->getHost() . " | Your branch in not set!"
            ]);
        }
        // تزریق شناسه شعبه به Request برای استفاده در لایه‌های بعدی
        $request->attributes->add(['branch' => $accessData['branch_id'], 'office' => $accessData['branch_id']]);
        return $next($request);
    }

    /**
     * استخراج دامنه پایه از رشته دامنه ورودی.
     *
     * دو تبدیل انجام می‌دهد:
     *  1. حذف شماره پورت (مثلاً 'example.com:8080' → 'example.com')
     *  2. حذف پیشوند 'www.' (مثلاً 'www.example.com' → 'example.com')
     *
     * @param  string $domainHeader  مقدار خام دامنه از هدر درخواست
     * @return string                دامنه پایه پاک‌شده
     */
    private function extractBaseDomain(string $domainHeader): string
    {
        $baseDomain = $domainHeader;

        // حذف پورت در صورت وجود
        if (str_contains($baseDomain, ':')) {
            $baseDomain = explode(':', $baseDomain)[0];
        }

        // حذف پیشوند www
        return str_replace('www.', '', $baseDomain);
    }

    /**
     * ساخت پاسخ خطای استانداردشده به فرمت JSON.
     *
     * ساختار پاسخ شامل وضعیت، زمان، جزئیات خطا و اطلاعات پشتیبانی است.
     * داده‌های $errorData با کد خطا merge شده و در کلید 'error' قرار می‌گیرند.
     *
     * @param  int   $code       کد خطای سطح اپلیکیشن
     * @param  array $errorData  جزئیات تکمیلی خطا (دامنه، هاست، پیام)
     * @param  int   $statusCode کد وضعیت HTTP (پیش‌فرض: ۴۰۰)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function createErrorResponse(int $code, array $errorData, int $statusCode = 400): Response
    {
        return response()->json([
            "status"  => false,
            "time"    => time(),
            "error"   => array_merge(["code" => $code], $errorData),
            "support" => config('support.contact')
        ], $statusCode);
    }
}
