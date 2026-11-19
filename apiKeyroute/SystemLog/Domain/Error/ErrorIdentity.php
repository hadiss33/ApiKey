<?php

namespace App\Services\SystemLog\Domain\Error;

/**
 * هویت یک خطا:
 *   fingerprint (md5): کلید گروه‌بندی خطاهای یکسان = شعبه + کلاس + فایل + خط + پیام نرمال‌شده؛ مسیر درخواست و کاربر در آن نیست.
 *     با dedupe_hours>0: پیام کنار می‌رود و «کاربر + شماره‌ی بازه‌ی N ساعته» اضافه می‌شود (هر کاربر ردیف خودش).
 *     برای خطای بدون کلاس و فایل (گزارش دستی) الگوی نرمال‌شده‌ی location هم وارد می‌شود.
 *   ref (کد پیگیری): «E-» + id در مبنای ۳۶ حروف بزرگ (مثل E-OA8)؛ در دیتابیس ذخیره نمی‌شود.
 */
final class ErrorIdentity
{
    public const REF_PREFIX = 'E-';

    /**
     * @param  int                 $windowHours  0 = خاموش (رفتار قبلی: پیام در کلید، گروه‌بندی دائمی، همه‌ی کاربرها یک ردیف). N>0 = «کاربر + نوع + مکان»
     *                                           (کاربر + کلاس + فایل + خط) بدون پیام در بازه‌های ثابت N ساعته یک ردیف است؛ بازه‌ی بعد ردیف تازه.
     *                                           یعنی هر کاربر ردیف خودش را دارد و تکرار او در بازه فقط شمارنده را بالا می‌برد.
     * @param  array<int, string>  $keepMessageFor  کلاس‌هایی (و زیرکلاس‌هایشان) که حتی با N>0 پیام در کلیدشان می‌ماند
     *                                              (مثل ApiException که از یک تابع مشترک با پیام‌های متفاوت می‌آید)
     */
    public static function fingerprint(ErrorEntry $entry, int $windowHours = 0, array $keepMessageFor = []): string
    {
        $parts = [
            (string) ($entry->branchId ?? 0),
            $entry->exceptionClass ?? '',
            $entry->file ?? '',
            (string) ($entry->line ?? ''),
        ];

        // گزارش دستی (بدون کلاس) همیشه پیامش هویت است؛ exception فقط وقتی «گروه‌بندی بر اساس مکان» روشن نباشد یا کلاسش استثنا باشد
        $byLocation = $windowHours > 0 && $entry->exceptionClass !== null && ! self::isAnyOf($entry->exceptionClass, $keepMessageFor);

        if (! $byLocation) {
            $parts[] = self::normalize($entry->message);
        }

        if ($windowHours > 0) {
            // کاربر (نوع:شناسه؛ ناشناس = 0:0) و شماره‌ی بازه‌ی ثابت N ساعته
            $parts[] = 'u' . ($entry->actorType ?? 0) . ':' . ($entry->actorId ?? 0);
            $parts[] = 'w' . intdiv($entry->occurredAt->getTimestamp(), $windowHours * 3600);
        }

        if ($entry->exceptionClass === null && $entry->file === null) {
            $parts[] = self::normalize((string) $entry->location);
        }

        return md5(implode('|', $parts));
    }

    /** @param  array<int, string>  $classes */
    private static function isAnyOf(string $class, array $classes): bool
    {
        foreach ($classes as $candidate) {
            if (is_a($class, ltrim($candidate, '\\'), true)) {
                return true;
            }
        }

        return false;
    }

    public static function ref(int $id): string
    {
        return self::REF_PREFIX . strtoupper(base_convert((string) $id, 10, 36));
    }

    /** id از کد پیگیری؛ قالب نامعتبر ← null */
    public static function idFromRef(string $ref): ?int
    {
        $ref = strtoupper(trim($ref));

        if (! str_starts_with($ref, self::REF_PREFIX)) {
            return null;
        }

        $body = substr($ref, strlen(self::REF_PREFIX));

        if ($body === '' || ! preg_match('/^[0-9A-Z]+$/', $body)) {
            return null;
        }

        $id = (int) base_convert($body, 36, 10);

        return $id > 0 ? $id : null;
    }

    /** UUID و hex بلند ← «?»، عددها ← «#»، فاصله‌ها فشرده، حداکثر ۲۰۰ کاراکتر (تا «Order 123» و «Order 456» یکی شوند) */
    private static function normalize(string $text): string
    {
        $text = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '?', $text) ?? $text;
        $text = preg_replace('/\b[0-9a-f]{16,}\b/i', '?', $text) ?? $text;
        $text = preg_replace('/\d+/', '#', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_substr($text, 0, 200);
    }
}
