<?php

namespace App\Services\SystemLog\Application\Contracts;

/**
 * پورت «اجرای فعلی»: request یا دستور artisan. وقتی request واقعی نیست (artisan، جاب، cron، یا خطا قبل از bind شدن request)
 * همه‌ی متدها null/false می‌دهند و هرگز exception نمی‌اندازند. پیاده‌سازی: LaravelRequestContext.
 * داده از attributeهای ست‌شده توسط میدلورها می‌آید (نه query/body کلاینت): branch ← DomainAccess، group و operator ← AuthWithJWT.
 */
interface RequestContext
{
    /**
     * نوع کاربر (گروه توکن JWT) ← عدد ذخیره‌شده (actor_type). base = اپراتور (جدول operators).
     * api_key = درخواست با کلید API (actor_id = id جدول api_keys؛ سرویس ApiKey).
     */
    public const ACTOR_TYPES = ['base' => 1, 'b2c' => 2, 'colleague' => 3, 'website' => 4, 'api_key' => 5];

    public function isHttp(): bool;

    /** آیا request فعلی مسیر api/* دارد؟ */
    public function isApiRequest(): bool;

    public function branchId(): ?int;

    /** عدد ACTOR_TYPES؛ null اگر گروه توکن نامشخص است */
    public function actorType(): ?int;

    public function actorId(): ?int;

    public function ip(): ?string;

    /** User-Agent خام (حداکثر ۲۵۵ کاراکتر) */
    public function userAgent(): ?string;

    /** «POST /api/v2/trade/store» در HTTP، «cmd:queue:work» در artisan، وگرنه null */
    public function location(): ?string;

    /**
     * جزئیات request برای ثبت خطا (method، query، body، headers فقط نام‌های داده‌شده، duration_ms، memory_mb)؛ بدون request null.
     * پاک‌سازی رازها کار لایه‌ی بالاتر است.
     *
     * @param  array<int, string>  $headerNames
     */
    public function requestDetails(array $headerNames): ?array;

    /** نام دستور artisan (مثل queue:work)؛ در HTTP null */
    public function consoleCommand(): ?string;

    /**
     * اولین آرگومان‌های خط فرمان (حداکثر $max).
     *
     * @return array<int, string>
     */
    public function consoleArguments(int $max = 3): array;
}
