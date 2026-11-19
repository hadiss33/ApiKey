<?php

namespace App\Services\SystemLog\Infrastructure\Notifications;

use App\Services\Notification\Application\DTOs\BotMessage;
use App\Services\Notification\Application\DTOs\NotificationMessage;
use App\Services\Notification\Application\Services\BotSubscriptionService;
use App\Services\Notification\Application\Services\NotificationManager;
use App\Services\SystemLog\Application\Contracts\AlertGateway;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * اعلان از سرویس Notification پروژه: صف (NotifyErrorJob)، پیامک (irnoti) و ربات‌های تلگرام/بله، با اعتبارنامه‌ی شعبه‌ی
 * notify.sender_branch. ربات فقط به اپراتوری می‌رسد که ربات را با همان موبایل تأیید کرده (bot_subscribers).
 */
final class ChannelAlertGateway implements AlertGateway
{
    public function __construct(
        private readonly NotificationManager $notifications,
        private readonly BotSubscriptionService $bots,
    ) {}

    public function queue(int $errorId): void
    {
        NotifyErrorJob::dispatch($errorId);
    }

    public function deliver(int $operatorId, string $mobile, AlertChannel $channel, string $text): bool
    {
        $branch = (int) config('system_log.notify.sender_branch', 1);

        try {
            $result = $channel->isBot()
                ? $this->bots->sendMessage($branch, $channel->value, $mobile, new BotMessage(text: $text, parseMode: 'HTML'))
                : $this->notifications->sendSms(new NotificationMessage(to: $mobile, body: $text, branch: $branch), 'irnoti');

            if (! $result->status) {
                Log::warning('SystemLog: error notification was not accepted by the provider', ['operator_id' => $operatorId, 'channel' => $channel->value, 'reason' => $result->message]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('SystemLog: error notification failed', ['operator_id' => $operatorId, 'channel' => $channel->value, 'failure' => $e->getMessage()]);

            return false;
        }
    }

    public function allow(int $operatorId, AlertChannel $channel): bool
    {
        $max = (int) config('system_log.notify.max_per_hour', 20);

        if ($max <= 0) {
            return true;
        }

        try {
            $key = sprintf('system_log:notify:%d:%s:%s', $operatorId, $channel->value, date('YmdH'));
            Cache::add($key, 0, 3600);

            return (int) Cache::increment($key) <= $max;
        } catch (Throwable) {
            // خرابی شمارنده نباید اعلان خطا را قطع کند
            return true;
        }
    }
}
