<?php

namespace App\Services\SystemLog\Application\Contracts;

use DateTimeInterface;

/** پورت ذخیره‌سازی ردپاها (پیاده‌سازی: EloquentTraceStore). */
interface TraceStore
{
    /**
     * نوشتن یک ردپا. ردیف: trace_id، branch_id، key، step، address، data (آرایه یا null)، created_at (DateTimeInterface).
     *
     * @param  array<string, mixed>  $row
     */
    public function store(array $row): void;

    /** حذف ردپاهای قدیمی‌تر از زمان داده‌شده (chunk به chunk)؛ تعداد حذف‌شده را برمی‌گرداند. */
    public function prune(DateTimeInterface $before): int;
}
