<?php

namespace App\Services\SystemLog\Infrastructure\Support;

use App\Services\SystemLog\Application\Services\TraceService;
use Throwable;

/**
 * نقطه‌ی استفاده‌ی ساده در کد (شبیه Log::info):
 *
 *     Trace::put('storeTemp', ['reference_id' => $id], '07.commit');
 *
 * وقتی SYSTEM_LOG_TRACE_ENABLED خاموش است هیچ چیزی ثبت نمی‌شود و هزینه‌اش فقط یک خواندن config است.
 * هرگز exception نمی‌اندازد.
 */
final class Trace
{
    public static function put(string $key, array $data = [], ?string $step = null): void
    {
        try {
            app(TraceService::class)->put($key, $data, $step);
        } catch (Throwable) {
            // Trace نباید request اصلی را خراب کند
        }
    }
}
