<?php

namespace App\Services\SystemLog\Application\DTOs;

use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Domain\Error\LogLevel;
use DateTimeInterface;

/**
 * یک خطا برای نمایش/تصمیم. source («کجا») و type («چه نوعی») شناسه‌ی دسته‌ها در ErrorTaxonomy هستند. stack و details فقط در جزئیات (find) پر می‌شوند،
 * نه در لیست و نه در نتیجه‌ی ثبت (ستون‌های سنگین).
 */
final class ErrorRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $ref,
        public readonly ?int $branchId,
        public readonly ?string $exceptionClass,
        public readonly int $type,
        public readonly LogLevel $level,
        public readonly ?string $code,
        public readonly string $message,
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly ErrorStatus $status,
        public readonly ?string $note,
        public readonly ?int $resolvedBy,
        public readonly ?DateTimeInterface $resolvedAt,
        public readonly ?DateTimeInterface $notifiedAt,
        public readonly int $occurrences,
        public readonly ?DateTimeInterface $firstSeenAt,
        public readonly ?DateTimeInterface $lastSeenAt,
        public readonly int $source,
        public readonly ?string $location,
        public readonly ?int $statusCode,
        public readonly ?int $actorType,
        public readonly ?int $actorId,
        public readonly ?string $ip,
        public readonly ?string $stack = null,
        public readonly ?array $details = null,
    ) {}
}
