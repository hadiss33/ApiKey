<?php

namespace App\Services\SystemLog\Application\DTOs;

use DateTimeInterface;

/** یک رکورد لاگ فعالیت برای نمایش. نام نمایشی نوع (titleFa/titleEn) از جدول نوع‌ها می‌آید (قابل تعیین در پنل). */
final class LogRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $titleFa,
        public readonly ?string $titleEn,
        public readonly string $category,
        public readonly ?string $subjectId,
        public readonly int $branchId,
        public readonly ?int $operatorId,
        public readonly ?string $operatorFirstName,
        public readonly ?string $operatorLastName,
        public readonly mixed $operatorPersonnelId,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly ?array $details,
        public readonly DateTimeInterface $createdAt,
    ) {}
}
