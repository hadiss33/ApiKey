<?php

namespace App\Services\SystemLog\Application\Contracts;

use App\Services\SystemLog\Domain\Access\AlertChannel;

/** پورت اعلان خطا (پیاده‌سازی: ChannelAlertGateway؛ صف + پیامک/تلگرام/بله با سرویس Notification پروژه). */
interface AlertGateway
{
    /** ارسال اعلان یک خطا را به صف می‌سپارد (ارسال واقعی کار جاب است، نه request). */
    public function queue(int $errorId): void;

    /** یک پیام را از کانال داده‌شده به یک موبایل می‌فرستد؛ هرگز exception نمی‌اندازد. */
    public function deliver(int $operatorId, string $mobile, AlertChannel $channel, string $text): bool;

    /**
     * آیا هنوز مجاز است به این اپراتور از این کانال اعلان بفرستیم؟ (سقف در ساعت؛ هر فراخوانی یک مصرف است)
     * خرابی شمارنده ← true: محدودکننده نباید اعلان خطا را قطع کند.
     */
    public function allow(int $operatorId, AlertChannel $channel): bool;
}
