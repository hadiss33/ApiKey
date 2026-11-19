<?php

namespace App\Services\SystemLog\Infrastructure\Notifications;

use App\Services\SystemLog\Application\Services\ErrorNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** ارسال اعلان یک خطا (پیامک/تلگرام/بله) روی fastJob؛ در شکست کامل ارسال ۳ بار با فاصله تلاش می‌کند. */
class NotifyErrorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> ثانیه */
    public array $backoff = [60, 300];

    public function __construct(public int $errorId)
    {
        $this->onQueue('fastJob');
    }

    public function handle(ErrorNotifier $notifier): void
    {
        $notifier->send($this->errorId);
    }
}
