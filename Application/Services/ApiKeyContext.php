<?php

namespace App\Services\ApiKey\Application\Services;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;

/**
 * تنها راهی که سرویس‌های دیگر هویت کلید درخواست فعلی را می‌خوانند.
 * میدلور apiKey رکورد را در attribute «api_key» می‌گذارد؛ بیرون از درخواست با کلید همه null است.
 *
 *   ApiKeyContext::current()  ← رکورد کلید یا null
 *   ApiKeyContext::actor()    ← ['type' => 'api_key', 'id' => 5] یا null (برای ثبت در رکوردها)
 */
final class ApiKeyContext
{
    public const ATTRIBUTE = 'api_key';

    public static function current(): ?ApiKeyRecord
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return null;
        }

        $record = request()->attributes->get(self::ATTRIBUTE);

        return $record instanceof ApiKeyRecord ? $record : null;
    }

    public static function id(): ?int
    {
        return self::current()?->id;
    }

    public static function branchId(): ?int
    {
        return self::current()?->branchId;
    }

    /** @return array{type: string, id: int}|null */
    public static function actor(): ?array
    {
        return self::current()?->actor();
    }
}
