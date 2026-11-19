<?php

namespace App\Services\SystemLog\Domain\Error;

use DateTimeInterface;

/**
 * یک وقوع خطا (ورودی ثبت). source = شناسه‌ی دسته‌ی «کجا رخ داد» و type = شناسه‌ی دسته‌ی «چه نوعی» (ErrorTaxonomy).
 * همه‌ی داده‌ها پس از حذف رازها و اعمال سقف حجم‌اند. fingerprint و کد پیگیری اینجا نیستند (ErrorIdentity).
 */
final class ErrorEntry
{
    public function __construct(
        public readonly int $source,
        public readonly int $type,
        public readonly LogLevel $level,
        public readonly string $message,
        public readonly ?string $exceptionClass,
        public readonly ?string $code,
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly ?int $branchId,
        public readonly ?int $statusCode,
        public readonly ?string $location,
        public readonly ?int $actorType,
        public readonly ?int $actorId,
        public readonly ?string $ip,
        public readonly ?string $stack,
        public readonly ?array $details,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
