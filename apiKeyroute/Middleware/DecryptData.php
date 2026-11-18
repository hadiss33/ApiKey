<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

/**
 * Class DecryptData
 *
 * میدلوور رمزگشایی داده‌های رمزشده ورودی.
 *
 * این میدلوور داده‌های رمزشده با RSA را که در فیلد 'data' درخواست ارسال
 * می‌شوند، با استفاده از کلید خصوصی متناظر با شعبه جاری رمزگشایی می‌کند.
 *
 * جریان اجرا:
 *  1. دریافت شناسه شعبه (branch) از attribute های Request
 *  2. خواندن داده رمزشده از فیلد 'data' درخواست
 *  3. بارگذاری کلید خصوصی متناظر با شعبه از فایل‌سیستم
 *  4. base64_decode کردن داده رمزشده
 *  5. رمزگشایی با openssl_private_decrypt
 *  6. json_decode کردن داده رمزگشایی‌شده
 *  7. جایگزینی فیلد 'data' در Request با داده پاک‌شده و ادامه زنجیره
 *
 * پیش‌نیاز: میدلوور DomainAccess یا IpTrust باید قبلاً اجرا شده
 * و مقدار 'branch' را به Request تزریق کرده باشد.
 *
 * @package App\Http\Middleware
 */
class DecryptData
{
    /**
     * کش سطح پروسه برای محتوای کلید خصوصی هر شعبه؛ از خواندن مجدد فایل
     * روی دیسک در هر درخواست جلوگیری می‌کند (کلید خصوصی هر شعبه ثابت است).
     *
     * @var array<string, string|false>
     */
    private static array $privateKeyCache = [];

    /**
     * پردازش درخواست ورودی.
     *
     * داده رمزشده را از فیلد 'data' خوانده، رمزگشایی کرده،
     * و مقدار اصلی را جایگزین می‌کند تا کنترلر با داده پاک کار کند.
     *
     * @param  \Illuminate\Http\Request                                                          $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // دریافت شناسه شعبه از attribute های تزریق‌شده توسط میدلوورهای قبلی
        $branch = $request->attributes->get('branch');

        // دریافت داده رمزشده از بدنه درخواست
        $encryptedData = $request->input('data');

        // بارگذاری کلید خصوصی RSA مخصوص این شعبه، با کش سطح پروسه تا در هر
        // درخواست دوباره از دیسک خوانده نشود
        $privateKey = self::$privateKeyCache[$branch] ??= (function () use ($branch) {
            $privateKeyPath = env('PRIVATE_KEY_PATH_OFFICE_' . $branch);
            return ($privateKeyPath && is_readable($privateKeyPath)) ? file_get_contents($privateKeyPath) : false;
        })();

        if ($privateKey === false) {
            return response()->json([
                "error" => [
                    "code"    => 1002,
                    "message" => "Decryption key is not configured for this branch.",
                ],
                "meta" => [
                    "timestamp" => time(),
                    "support"   => config('support.contact')
                ],
            ], 400);
        }

        // تبدیل داده از base64 به باینری خام برای رمزگشایی
        $encryptedData = base64_decode($encryptedData, true);

        // رمزگشایی داده با کلید خصوصی RSA. توجه: padding به‌صراحت PKCS1 (همان پیش‌فرض) نگه داشته شده
        // چون تغییر به OAEP نیازمند هماهنگی سمت کلاینت‌هاست (پارتنرها با همین padding رمزنگاری می‌کنند)
        // و بدون آن تمام درخواست‌های واقعی decrypt نخواهند شد.
        $decryptedData = '';
        $decryptionSuccess = $encryptedData !== false
            && openssl_private_decrypt($encryptedData, $decryptedData, $privateKey, OPENSSL_PKCS1_PADDING);

        // تبدیل JSON رمزگشایی‌شده به آرایه PHP
        $data = $decryptionSuccess ? json_decode($decryptedData, true) : null;

        if ($decryptionSuccess && $data !== null) {
            // حذف داده رمزشده و جایگزینی با داده پاک در Request
            $request->offsetUnset('data');
            $request->merge(['data' => $data]);
            return $next($request);
        } else {
            // رمزگشایی ناموفق بود؛ برگرداندن خطای مناسب
            return response()->json([
                "error" => [
                    "code"    => 1001,
                    "message" => "Decryption failed.",
                ],
                "meta" => [
                    "timestamp" => time(),
                    "support"   => config('support.contact')
                ],
            ], 400);
        }
    }
}
