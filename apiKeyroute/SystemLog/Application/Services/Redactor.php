<?php

namespace App\Services\SystemLog\Application\Services;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use stdClass;

/**
 * پاک‌سازی رازها قبل از ذخیره (توکن، Authorization، رمز، OTP و ...) و محدود کردن حجم.
 * فقط «راز» پاک می‌شود، نه داده‌ی کسب‌وکار (مبلغ، موبایل، مشخصات مسافر)؛ چون همان‌ها برای دیباگ لازم‌اند.
 * لیست کلیدها از config('system_log.redact_keys' و 'redact_key_contains') می‌آید.
 */
class Redactor
{
    public const MASK = '[redacted]';

    private const MAX_DEPTH = 8;

    /**
     * مقدار کلیدهای حساس را پاک می‌کند و رشته‌ها را از الگوهای راز (Bearer، token=...) پاک‌سازی می‌کند.
     */
    public function redact(array $data, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated' => 'max depth'];
        }

        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key)) {
                $out[$key] = self::MASK;
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = $this->scrubString($value);
            } elseif ($value === null || is_scalar($value)) {
                $out[$key] = $value;
            } elseif ($value instanceof DateTimeInterface) {
                $out[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value) && ($asArray = $this->objectToArray($value)) !== null) {
                // stdClass (ردیف DB)، مدل Eloquent و ... به آرایه تبدیل می‌شوند تا محتوایشان از بین نرود
                $out[$key] = $this->redact($asArray, $depth + 1);
            } else {
                // آبجکت ناشناخته/resource: فقط نوعش ثبت می‌شود تا serialize خطا ندهد
                $out[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        return $out;
    }

    /**
     * رازهای داخل متن آزاد (پیام خطا، URL) را پاک می‌کند: «Bearer xxx» و «token=xxx».
     */
    public function scrubString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer ' . self::MASK, $value) ?? $value;

        return preg_replace(
            '/\b(password|passwd|secret|access_token|refresh_token|token|api_key|apikey|otp)=([^&\s"\']+)/i',
            '$1=' . self::MASK,
            $value
        ) ?? $value;
    }

    /**
     * رشته‌های بلند را کوتاه می‌کند و اگر JSON هنوز از سقف بزرگ‌تر بود، بزرگ‌ترین کلیدهای سطح اول را
     * «[truncated]» می‌کند تا حجم کنترل شود.
     */
    public function fit(array $data, int $maxBytes, int $maxString): array
    {
        $data = $this->truncateStrings($data, $maxString);

        if ($this->size($data) <= $maxBytes) {
            return $data;
        }

        $sizes = [];
        foreach ($data as $key => $value) {
            $sizes[$key] = $this->size($value);
        }
        arsort($sizes);

        foreach (array_keys($sizes) as $key) {
            $data[$key] = '[truncated]';

            if ($this->size($data) <= $maxBytes) {
                break;
            }
        }

        return $data;
    }

    /** تبدیل آبجکت‌های داده‌ای به آرایه؛ برای بقیه‌ی آبجکت‌ها null */
    private function objectToArray(object $value): ?array
    {
        return match (true) {
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof JsonSerializable => is_array($serialized = $value->jsonSerialize()) ? $serialized : null,
            $value instanceof stdClass => get_object_vars($value),
            default => null,
        };
    }

    private function isSecretKey(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        if (in_array($normalized, (array) config('system_log.redact_keys', []), true)) {
            return true;
        }

        foreach ((array) config('system_log.redact_key_contains', []) as $needle) {
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function truncateStrings(array $data, int $maxString): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->truncateStrings($value, $maxString);
            } elseif (is_string($value) && mb_strlen($value) > $maxString) {
                $data[$key] = mb_substr($value, 0, $maxString) . '...';
            }
        }

        return $data;
    }

    private function size(mixed $value): int
    {
        return strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
