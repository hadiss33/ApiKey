<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\Contracts\TraceStore;
use Throwable;

/**
 * ردپای دیباگ برای برنامه‌نویس (مثل dd ولی بدون متوقف کردن برنامه): Trace::put('کلید', [داده], 'مرحله').
 * هر put همان لحظه یک ردیف می‌نویسد. خاموش (trace.enabled=false) = فقط یک خواندن config. هرگز exception نمی‌اندازد.
 * trace_id فقط stepهای «همان request/جاب» را به هم وصل می‌کند (با startContext/endContext برای هر جاب تازه می‌شود).
 */
final class TraceService
{
    private ?string $traceId = null;

    private ?string $address = null;

    public function __construct(
        private readonly TraceStore $store,
        private readonly Redactor $redactor,
        private readonly RequestContext $request,
    ) {}

    public function put(string $key, array $data = [], ?string $step = null): void
    {
        if (! config('system_log.trace.enabled', false)) {
            return;
        }

        try {
            $data = $this->redactor->fit(
                $this->redactor->redact($data),
                (int) config('system_log.trace.max_data_bytes', 4096),
                (int) config('system_log.trace.max_string_length', 500),
            );

            $address = $this->address ?? $this->request->location();

            $this->store->store([
                'trace_id' => $this->traceId ??= bin2hex(random_bytes(6)),
                'branch_id' => $this->request->branchId(),
                'key' => mb_substr($key, 0, 64),
                'step' => $step !== null ? mb_substr($step, 0, 96) : null,
                'address' => $address !== null ? mb_substr($address, 0, 300) : null,
                'data' => $data === [] ? null : $data,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Trace نباید برنامه‌ی اصلی را خراب کند
        }
    }

    /** شروع زمینه‌ی جدید (مثلاً یک جاب): trace_id و آدرس تازه */
    public function startContext(?string $address = null): void
    {
        $this->traceId = null;
        $this->address = $address;
    }

    public function endContext(): void
    {
        $this->startContext();
    }

    /** حذف ردپاهای قدیمی‌تر از retention_days (پیش‌فرض ۷ روز) */
    public function prune(): int
    {
        return $this->store->prune(now()->subDays(max(1, (int) config('system_log.trace.retention_days', 7))));
    }
}
