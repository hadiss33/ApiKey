<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class AuthWithJWT
 *
 * میدلوور احراز هویت درخواست‌های HTTP با استفاده از JWT (JSON Web Token).
 *
 * این میدلوور توکن Bearer را از هدر Authorization استخراج می‌کند،
 * صحت و اعتبار آن را بررسی می‌کند، سپس بر اساس نوع کاربر (typ)
 * رکورد کاربر را از جدول مناسب در دیتابیس پیدا می‌کند.
 *
 * انواع کاربری پشتیبانی‌شده (تعریف‌شده در USER_TABLES):
 *  - 'base'      → جدول operators       (کاربران ادمین / بک‌آفیس)
 *  - 'b2c'       → جدول passengers      (مسافران / کاربران عادی)
 *  - 'colleague' → جدول colleague_auth  (کارمندان داخلی، با JOIN به جدول colleagues)
 *
 * در صورت موفقیت، اطلاعات زیر به Request اضافه می‌شود تا در لایه‌های بعدی قابل استفاده باشد:
 *  - 'group'    : نوع کاربر از توکن (مثلاً 'base'، 'b2c'، 'colleague')
 *  - 'level'    : سطح دسترسی کاربر (در صورت نبود، مقدار پیش‌فرض ۱)
 *  - 'operator' : آبجکت کاربر دریافت‌شده از دیتابیس
 *
 * @package App\Http\Middleware
 */
class AuthWithJWT
{
    /**
     * نگاشت نوع توکن به نام جدول متناظر در دیتابیس.
     *
     * مقدار claim با کلید 'typ' در پیلود JWT مشخص می‌کند
     * که کاربر باید از کدام جدول واکشی شود.
     *
     * @var array<string, string>
     */
    private const USER_TABLES = [
        'base'      => 'operators',
        'b2c'       => 'passengers',
        'colleague' => 'colleague_auth',
        // کاربر وب‌سایت که هنوز پروفایل مسافر نساخته است.
        'website'   => 'passenger_website_users',
    ];

    /**
     * پردازش درخواست ورودی.
     *
     * مراحل اجرای احراز هویت:
     *  1. استخراج توکن Bearer از هدر Authorization
     *  2. رمزگشایی و تأیید امضای رمزنگاری JWT
     *  3. بررسی وجود claim با کلید 'uuid' در توکن
     *  4. واکشی رکورد کاربر از جدول مناسب بر اساس claim با کلید 'typ'
     *  5. بررسی فعال بودن حساب کاربری (status = 1)
     *  6. تزریق اطلاعات کاربر به Request و ادامه زنجیره میدلوور
     *
     * هر استثنای پیش‌بینی‌نشده‌ای منجر به پاسخ ۵۰۰ با اطلاعات
     * فایل و خط خطا می‌شود (مناسب برای محیط‌های غیر production).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // بررسی وجود توکن در درخواست
            $token = $request->bearerToken();
            if (!$token) {
                return $this->errorResponse(1005, __('auth.token_not_provided'), 400);
            }

            // رمزگشایی و تأیید توکن JWT
            try {
                JWT::$leeway = 60; // تحمل ۶۰ ثانیه اختلاف ساعت بین سرورها
                $decoded = JWT::decode($token, new Key(config('jwt.secret_key'), 'HS256'));
            } catch (\Exception $e) {
                return $this->errorResponse(1006, __('auth.token_expired'), 401);
            }

            // بررسی وجود claim با کلید uuid در توکن
            if (!isset($decoded->uuid)) {
                return $this->errorResponse(1004, __('auth.not_have_access_permission', ['access' => 'سریال']), 403);
            }

            // واکشی کاربر از جدول متناسب با نوع توکن
            $user = $this->getUser($decoded->typ, $decoded->uuid);

            if (!$user) {
                return $this->errorResponse(1003, __('auth.not_have_access_permission', ['access' => 'اطلاعات کافی']), 403);
            }

            // بررسی فعال بودن حساب کاربری
            if ($user->status != 1) {
                return $this->errorResponse(1002, __('auth.not_have_access_permission', ['access' => 'وضعیت حساب']), 403);
            }

            // افزودن اطلاعات کاربر به Request و ارسال به میدلوور بعدی
            $request->attributes->add([
                'group'    => $decoded->typ,
                'level'    => $user->level ?? 1,
                'operator' => $user
            ]);

            return $next($request);
        } catch (\Exception $e) {
            return $this->errorResponse(
                (int) $e->getCode(),
                $e->getMessage(),
                500,
                app()->environment('local') ? [
                    "file" => str_replace(base_path(), '', $e->getFile()),
                    "line" => $e->getLine()
                ] : []
            );
        }
    }

    /**
     * واکشی رکورد کاربر از جدول مناسب بر اساس نوع توکن.
     *
     * برای نوع 'colleague' یک LEFT JOIN با جدول 'colleagues' انجام می‌شود
     * تا ID اصلی کارمند در کنار اطلاعات احراز هویت برگردانده شود.
     * برای سایر انواع، رکورد مستقیماً با کلید اصلی (uuid) واکشی می‌شود.
     *
     * در صورتی که نوع توکن در USER_TABLES تعریف نشده باشد، null برمی‌گردد
     * که توسط تابع فراخوان به عنوان خطای دسترسی تفسیر می‌شود.
     *
     * @param  string   $type  نوع کاربر از claim با کلید 'typ' در توکن JWT
     * @param  int      $uuid  شناسه کاربر از claim با کلید 'uuid' در توکن JWT
     * @return object|null     رکورد کاربر، یا null در صورت نبود / نوع ناشناخته
     */
    private function getUser(string $type, int $uuid)
    {
        if ($type == 'trusted') {
            $type = 'base';
        }

        $table = self::USER_TABLES[$type] ?? null;

        if (!$table) {
            return null;
        }

        if ($type == 'colleague') {
            return DB::table($table)
                ->select(
                    'colleague_auth.*',
                    'colleagues.id as colleague_id'
                )
                ->leftJoin('colleagues', 'colleague_auth.colleague', 'colleagues.id')
                ->where('colleague_auth.id', $uuid)
                ->first();
        }

        return DB::table($table)->where('id', $uuid)->first();
    }

    /**
     * ساخت پاسخ خطای استانداردشده به فرمت JSON.
     *
     * تمام پاسخ‌های خطا از یک ساختار یکسان پیروی می‌کنند
     * تا مصرف‌کننده API بتواند آن‌ها را به شکل یکنواخت پردازش کند.
     * بلوک 'meta' همیشه شامل timestamp یونیکس و اطلاعات پشتیبانی است.
     *
     * پارامتر $additionalData در صورت ارسال، به آبجکت 'error' merge می‌شود —
     * در حال حاضر فقط برای نمایش فایل و خط خطا در استثناهای پیش‌بینی‌نشده استفاده می‌شود.
     *
     * راهنمای کدهای خطا:
     *  - 1002 : حساب کاربری غیرفعال یا مسدود است
     *  - 1003 : کاربر در جدول مربوطه یافت نشد
     *  - 1004 : توکن فاقد claim با کلید 'uuid' است
     *  - 1005 : توکن Bearer در درخواست وجود ندارد
     *  - 1006 : توکن معتبر نیست یا منقضی شده است
     *
     * @param  int    $code            کد خطای سطح اپلیکیشن
     * @param  string $message         پیام خطای قابل فهم برای انسان
     * @param  int    $httpCode        کد وضعیت HTTP پاسخ
     * @param  array  $additionalData  فیلدهای اضافی اختیاری برای merge در آبجکت error
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse(int $code, string $message, int $httpCode, array $additionalData = [])
    {
        $response = [
            "error" => array_merge([
                "code"    => $code,
                "message" => $message
            ], $additionalData),
            "meta" => [
                "timestamp" => time(),
                "support"   => config('support.contact')
            ]
        ];

        return response()->json($response, $httpCode);
    }
}
