<?php

namespace App\Services\SystemLog\Domain\Access;

use RuntimeException;

/**
 * خطای دسترسی/تنظیم دسترسی. پیام همیشه «کلید lang» است (مثل system_log.access.manager_only)؛ ترجمه با لایه‌ی بالاتر.
 * forbidden ← ۴۰۳ (اجازه ندارد)، invalid ← ۴۲۲ (ورودی نامعتبر).
 */
final class AccessException extends RuntimeException
{
    private function __construct(string $messageKey, public readonly bool $forbidden)
    {
        parent::__construct($messageKey);
    }

    public static function forbidden(string $messageKey): self
    {
        return new self($messageKey, true);
    }

    public static function invalid(string $messageKey): self
    {
        return new self($messageKey, false);
    }
}
