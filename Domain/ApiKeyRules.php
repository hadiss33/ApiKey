<?php

namespace App\Services\ApiKey\Domain;

use DateTimeInterface;

/**
 * قانون‌های خالص کلید API (بدون لاراول): نوع و محیط، تولید کلید و پیشوند نمایشی، وضعیت،
 * نرمال‌سازی و تطبیق دامنه (Origin)، و تطبیق IP/CIDR.
 */
final class ApiKeyRules
{
    // نوع کلید (ستون tinyint api_keys.type)
    public const TYPE_SECRET = 1; // سرور به سرور
    public const TYPE_PUBLIC = 2; // فرانت/اپ (کد قابل دیدن)

    public const TYPES = ['secret' => self::TYPE_SECRET, 'public' => self::TYPE_PUBLIC];

    // محیط (ستون tinyint api_keys.environment)
    public const ENV_LIVE = 1;
    public const ENV_TEST = 2;

    public const ENVIRONMENTS = ['live' => self::ENV_LIVE, 'test' => self::ENV_TEST];

    // سطح دسترسی (ستون tinyint api_key_grants.level): GET/HEAD = خواندن، بقیه‌ی متدها = نوشتن
    public const LEVEL_READ = 1;
    public const LEVEL_WRITE = 2;

    public const LEVELS = ['read' => self::LEVEL_READ, 'write' => self::LEVEL_WRITE];

    /** سطح لازم یک متد HTTP: GET/HEAD/OPTIONS ← read، بقیه ← write */
    public static function levelForMethod(string $method): int
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) ? self::LEVEL_READ : self::LEVEL_WRITE;
    }

    /**
     * آیا سطح موجود ($actual) سطح لازم ($required) را پوشش می‌دهد؟
     * write (۲) شامل read (۱) هم هست؛ read فقط read را پوشش می‌دهد.
     */
    public static function levelCovers(int $required, int $actual): bool
    {
        return $actual >= $required;
    }

    public static function levelKey(int $level): string
    {
        return array_search($level, self::LEVELS, true) ?: 'read';
    }

    /**
     * prefix نرمال‌شده‌ی یک مسیر: بدون «/» اول و آخر، بخش‌بخش. «api/v2/system-log/» ← ['api','v2','system-log'].
     *
     * @return array<int, string>
     */
    public static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));
    }

    /** آیا $prefix (بخش‌بخش) ابتدای $uri است؟ «errors» با «errors-archive» اشتباه گرفته نمی‌شود. */
    public static function hasPrefix(array $uriSegments, array $prefixSegments): bool
    {
        return $prefixSegments !== [] && array_slice($uriSegments, 0, count($prefixSegments)) === $prefixSegments;
    }

    // وضعیت محاسبه‌ای (ستون ندارد)
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /** تعداد کاراکتر تصادفی که بعد از پیشوند در نمایش پنل می‌آید */
    private const DISPLAY_CHARS = 4;

    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function typeKey(int $type): string
    {
        return array_search($type, self::TYPES, true) ?: 'secret';
    }

    public static function environmentKey(int $environment): string
    {
        return array_search($environment, self::ENVIRONMENTS, true) ?: 'live';
    }

    /** پیشوند کامل کلید: erp_sk_ یا erp_sk_test_ */
    public static function prefixFor(int $type, int $environment, array $prefixes): string
    {
        $base = (string) ($prefixes[self::typeKey($type)] ?? 'erp_key_');

        return $environment === self::ENV_TEST ? $base . 'test_' : $base;
    }

    /** کلید خام تازه: پیشوند + $length کاراکتر تصادفی امن (base62) */
    public static function generate(string $prefix, int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $random = '';

        for ($i = 0; $i < max(32, $length); $i++) {
            $random .= self::ALPHABET[random_int(0, $max)];
        }

        return $prefix . $random;
    }

    /** بخشی از کلید که در پنل نمایش داده می‌شود: erp_sk_8fK2 */
    public static function displayPrefix(string $plainKey, string $prefix): string
    {
        return substr($plainKey, 0, strlen($prefix) + self::DISPLAY_CHARS);
    }

    /** آیا رشته شبیه کلید این سیستم است (یکی از پیشوندها + کاراکتر مجاز)؟ */
    public static function looksLikeKey(string $value, array $prefixes): bool
    {
        if (strlen($value) > 120 || ! preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function status(?DateTimeInterface $revokedAt, ?DateTimeInterface $expiresAt, DateTimeInterface $now): string
    {
        return match (true) {
            $revokedAt !== null => self::STATUS_REVOKED,
            $expiresAt !== null && $expiresAt <= $now => self::STATUS_EXPIRED,
            default => self::STATUS_ACTIVE,
        };
    }

    /**
     * Origin نرمال: «scheme://host[:port]» با حروف کوچک، بدون مسیر و / آخر. فقط http/https؛ نامعتبر ← null.
     * پورت پیش‌فرض (80/443) حذف می‌شود تا https://a.ir و https://a.ir:443 یکی باشند.
     */
    public static function normalizeOrigin(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 255) {
            return null;
        }

        $parts = parse_url(str_contains($value, '://') ? $value : 'https://' . $value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if ($port === ($scheme === 'https' ? 443 : 80)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }

    /** آیا قانون IP معتبر است؟ (IPv4/IPv6 تکی یا CIDR) */
    public static function validIpRule(string $rule): bool
    {
        [$ip, $bits] = array_pad(explode('/', trim($rule), 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($bits === null) {
            return true;
        }

        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= $max;
    }

    /** آیا IP در یکی از قانون‌ها (IP تکی یا CIDR) می‌گنجد؟ فهرست خالی = بدون محدودیت. */
    public static function ipAllowed(?string $ip, array $rules): bool
    {
        if ($rules === []) {
            return true;
        }

        $packed = $ip !== null ? @inet_pton($ip) : false;

        if ($packed === false) {
            return false;
        }

        foreach ($rules as $rule) {
            [$base, $bits] = array_pad(explode('/', (string) $rule, 2), 2, null);
            $range = @inet_pton((string) $base);

            if ($range === false || strlen($range) !== strlen($packed)) {
                continue;
            }

            $bits = $bits === null ? strlen($packed) * 8 : (int) $bits;

            if (self::samePrefix($packed, $range, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function samePrefix(string $a, string $b, int $bits): bool
    {
        $bytes = intdiv($bits, 8);

        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }

        $rest = $bits % 8;

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }
}
