<?php

namespace App\Services\SystemLog\Domain\Audit;

use DateTimeInterface;

/**
 * اطلاعات request در «لحظه‌ی وقوع» یک رویداد فعالیت (هنگام dispatch جاب ثبت می‌شود، نه هنگام اجرای آن).
 * operatorId فقط برای کاربر اپراتور؛ کاربران دیگر در actor (['type' => 'b2c', 'id' => 5]).
 */
final class AuditContext
{
    public function __construct(
        public readonly int $branchId,
        public readonly ?int $operatorId,
        public readonly ?array $actor,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
