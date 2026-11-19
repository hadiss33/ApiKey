<?php

namespace App\Services\SystemLog\Domain\Access;

/**
 * کانال اعلان خطا؛ هر کاربر دقیقاً یک کانال دارد (ستون system_log_access.channel) و اعلان فقط از همان می‌رود.
 * پیامک کوتاه است؛ ربات‌ها (تلگرام/بله) پیام HTML کامل‌تر می‌گیرند و باید موبایل کاربر در ربات تأیید شده باشد.
 */
enum AlertChannel: string
{
    case Sms = 'sms';
    case Telegram = 'telegram';
    case Bale = 'bale';

    public function isBot(): bool
    {
        return $this !== self::Sms;
    }
}
