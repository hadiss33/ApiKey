<?php

namespace App\Services\ApiKey\Domain;

use RuntimeException;

/**
 * خطای قابل نمایش ماژول ApiKey. پیام = کلید ترجمه (api_key.*)، نه متن خام.
 * $status کد HTTP و $errorCode کد خطای خروجی (قالب error.code) است؛ $fields برای خطای فیلدی (۴۲۲).
 */
final class ApiKeyException extends RuntimeException
{
    /** @param  array<string, array<int, string>>  $fields */
    private function __construct(
        string $translationKey,
        public readonly int $status,
        public readonly int $errorCode,
        public readonly array $fields = [],
        public readonly array $replace = [],
        public readonly array $required = [],
    ) {
        parent::__construct($translationKey);
    }

    /** ورودی نامعتبر؛ $field اختیاری برای نمایش زیر همان فیلد */
    public static function invalid(string $translationKey, ?string $field = null, array $replace = []): self
    {
        return new self($translationKey, 422, 4220, $field !== null ? [$field => [$translationKey]] : [], $replace);
    }

    public static function forbidden(string $translationKey = 'api_key.errors.forbidden'): self
    {
        return new self($translationKey, 403, 4030);
    }

    public static function notFound(): self
    {
        return new self('api_key.errors.not_found', 404, 4040);
    }

    /** کلید نامعتبر/منقضی/باطل — عمداً یک پیام برای همه تا علت افشا نشود */
    public static function unauthenticated(): self
    {
        return new self('api_key.auth.invalid_key', 401, 4010);
    }

    /** کلید معتبر است ولی اپراتور سازنده‌اش حذف/غیرفعال شده (عملیات با هویت او انجام می‌شود، پس کلید کار نمی‌کند) */
    public static function creatorInactive(): self
    {
        return new self('api_key.auth.creator_inactive', 401, 4011);
    }

    /** IP یا دامنه‌ی مجاز نیست */
    public static function sourceDenied(): self
    {
        return new self('api_key.auth.source_denied', 403, 4031);
    }

    /**
     * کلید معتبر است ولی این بخش/سطح را ندارد (یا route برای کلید باز نیست).
     * $required فقط برای کلید معتبر برمی‌گردد (نشتی نیست): ['category' => 'system_log', 'level' => 'write'].
     *
     * @param  array<string, string>  $required
     */
    public static function scopeDenied(array $required = []): self
    {
        return new self('api_key.auth.scope_denied', 403, 4032, [], [], $required);
    }
}
