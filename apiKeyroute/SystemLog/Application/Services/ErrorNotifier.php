<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\AlertGateway;
use App\Services\SystemLog\Application\Contracts\ErrorStore;
use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Domain\Error\LogLevel;
use RuntimeException;

/**
 * اعلان خطا (پیامک/تلگرام/بله): برای هر خطای یکتای «جدید» یا «برگشته» (نه هر تکرار) به کاربرانی که آن خطا در دسته‌هایشان
 * هست؛ با کانال‌هایی که مدیر برایشان تعیین کرده. notified_at با اولین ثبت خالی است، بعد از اعلان پر می‌شود و با برگشت
 * خطای resolved دوباره خالی می‌شود.
 *
 *   consider(): داخل request، ارزان ← claim اتمیک ← صف
 *   send():     داخل جاب؛ ساخت متن و ارسال با سقف نرخ
 */
final class ErrorNotifier
{
    public function __construct(
        private readonly ErrorStore $errors,
        private readonly AccessService $access,
        private readonly AlertGateway $gateway,
        private readonly CategoryService $categories,
    ) {}

    public function consider(ErrorRecord $record): void
    {
        if (! config('system_log.notify.enabled', false) || $record->notifiedAt !== null || $record->status === ErrorStatus::Ignored) {
            return;
        }

        if ($record->level->value < (int) config('system_log.notify.min_level', LogLevel::Error->value)) {
            return;
        }

        // بدون گیرنده claim نمی‌کنیم تا بعد از تنظیم گیرنده‌ها همین خطای هنوز-باز هم اعلان شود
        if ($this->access->recipientsFor($record->source, $record->type) === []) {
            return;
        }

        if ($this->errors->claimNotification($record->id)) {
            $this->gateway->queue($record->id);
        }
    }

    /**
     * به هر گیرنده فقط از «یک» کانالش می‌فرستد. اگر هیچ ارسالی موفق نشود و حداقل یکی شکست خورده باشد exception می‌اندازد
     * تا صف retry کند (با یک موفق، نه تا پیام تکراری نرود).
     */
    public function send(int $errorId): void
    {
        $record = $this->errors->find($errorId);

        if ($record === null) {
            return;
        }

        $sent = $failed = 0;

        foreach ($this->access->recipientsFor($record->source, $record->type) as $recipient) {
            $channel = $recipient['channel'];

            if (! $this->gateway->allow($recipient['operator_id'], $channel)) {
                continue;
            }

            $this->gateway->deliver($recipient['operator_id'], $recipient['mobile'], $channel, $this->message($record, $channel)) ? $sent++ : $failed++;
        }

        if ($sent === 0 && $failed > 0) {
            throw new RuntimeException('SystemLog: every error notification failed');
        }
    }

    /** برای ربات‌ها HTML (مقدارها escape می‌شوند)، برای پیامک کوتاه‌تر */
    public function message(ErrorRecord $record, AlertChannel $channel): string
    {
        $bot = $channel->isBot();
        $tax = $this->categories->taxonomy();
        $locale = app()->getLocale();

        $values = [
            'level' => __('system_log.levels.' . $record->level->key()),
            'category' => $tax->title($record->type, $locale),
            'source' => $tax->title($record->source, $locale),
            'ref' => $record->ref,
            'location' => mb_substr((string) $record->location, 0, $bot ? 120 : 60),
            'message' => mb_substr($record->message, 0, (int) config('system_log.notify.message_length.' . ($bot ? 'bot' : 'sms'), $bot ? 300 : 100)),
            'branch' => $record->branchId === null ? '-' : (string) $record->branchId,
            'count' => (string) $record->occurrences,
        ];

        if ($bot) {
            $values = array_map(fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $values);
        }

        return __('system_log.notify.' . ($bot ? 'bot' : 'sms'), $values);
    }
}
