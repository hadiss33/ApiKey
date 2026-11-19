<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\AuditStore;
use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\DTOs\LogRecord;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Access\AccessException;
use App\Services\SystemLog\Domain\Audit\AuditContext;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

/**
 * لاگ فعالیت («چه کسی، چه کاری، روی چه موجودیتی»).
 *
 * ثبت در دو مرحله (جاب صف App\Jobs\SystemLog آن‌ها را جدا می‌کند):
 *   ۱) هنگام dispatch داخل request: captureContext() ← شعبه، اپراتور، IP، مرورگر، زمان وقوع
 *   ۲) داخل جاب: record() ← حذف رازها، سقف حجم، یک ردیف به‌ازای هر goal
 *
 * قواعد: همیشه شعبه‌دار (بدون شعبه = audit.no_branch_id)؛ operator_id فقط برای کاربر اپراتور،
 * بقیه‌ی کاربران در details._actor؛ داده‌ی کسب‌وکار کامل می‌ماند و فقط رازها پاک می‌شوند؛ توکن هرگز ذخیره نمی‌شود.
 * نوع رویداد داده است (AuditStore::resolveType)، نه کد.
 * شکست ثبت عمداً پنهان نمی‌شود تا صف retry کند و رویداد ممیزی بی‌صدا گم نشود.
 */
final class AuditService
{
    public function __construct(
        private readonly AuditStore $store,
        private readonly Redactor $redactor,
        private readonly RequestContext $request,
    ) {}

    /** باید داخل request فراخوانی شود. */
    public function captureContext(): AuditContext
    {
        $type = $this->request->actorType();
        $id = $this->request->actorId();
        $isOperator = $type === null || $type === RequestContext::ACTOR_TYPES['base'];

        return new AuditContext(
            branchId: $this->request->branchId() ?? (int) config('system_log.audit.no_branch_id', 0),
            operatorId: $isOperator ? $id : null,
            actor: (! $isOperator && $id !== null) ? ['type' => array_search($type, RequestContext::ACTOR_TYPES, true), 'id' => $id] : null,
            ip: $this->request->ip(),
            userAgent: $this->request->userAgent(),
            occurredAt: now(),
        );
    }

    /**
     * @param  mixed  $data  آرایه، آبجکت (ردیف DB)، مقدار ساده یا null
     * @param  mixed  $goal  شناسه‌ی موجودیت: مقدار ساده، آرایه‌ای از آن‌ها، یا null (یک ردیف به‌ازای هر عضو)
     * @return int تعداد ردیف ثبت‌شده
     */
    public function record(string $type, mixed $data, mixed $goal, AuditContext $context): int
    {
        $code = mb_substr(trim($type), 0, 48);

        if ($code === '') {
            Log::warning('SystemLog: audit log skipped because the type is empty');

            return 0;
        }

        $typeId = $this->store->resolveType($code);
        $details = $this->details($data, $context);
        $count = 0;

        foreach ($this->subjects($goal) as $subject) {
            $this->store->store($typeId, $subject, $context, $details);
            $count++;
        }

        return $count;
    }

    /** @return array<int, LogRecord> */
    public function history(string $typeOrCategory, string $subjectId, int $branchId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, int $limit = 100): array
    {
        return $this->store->history($typeOrCategory, $subjectId, $branchId, $from, $to, $limit);
    }

    /**
     * لاگ‌های «یک شعبه» (شعبه‌ی request اپراتور)؛ دسترسی دیگری لازم نیست.
     *
     * @param  array<string, mixed>  $filters
     * @return Page<LogRecord>
     */
    public function search(array $filters, int $branchId, int $page = 1, int $perPage = 20): Page
    {
        return $this->store->search($filters, $branchId, $page, $perPage);
    }

    /** @return array<int, array{id: int, code: string, category: string, title_fa: ?string, title_en: ?string}> */
    public function types(): array
    {
        return $this->store->types();
    }

    /** تعیین دسته و نام نمایشی یک نوع رویداد (کنترل «فقط مدیر» با فراخوان)؛ false اگر نوع نباشد */
    public function updateType(int $id, ?string $category, ?string $titleFa, ?string $titleEn): bool
    {
        if ($category !== null && ! preg_match('/^[a-z][a-z0-9_]{1,31}$/', $category)) {
            throw AccessException::invalid('system_log.category.invalid');
        }

        return $this->store->updateType($id, $category, $titleFa, $titleEn);
    }

    private function details(mixed $data, AuditContext $context): ?array
    {
        $details = null;

        if ($data !== null && $data !== '') {
            $normalized = $this->redactor->redact(['data' => $data])['data'];
            $details = is_array($normalized) ? $normalized : ['value' => $normalized];
        }

        if ($context->actor !== null) {
            $details = ($details ?? []) + ['_actor' => $context->actor];
        }

        if ($details === null || $details === []) {
            return null;
        }

        return $this->redactor->fit(
            $details,
            (int) config('system_log.audit.max_details_bytes', 65536),
            (int) config('system_log.audit.max_string_length', 10000),
        );
    }

    /**
     * goal ← فهرست subject_id (حداکثر ۶۴ کاراکتر)؛ عضو آرایه‌ای ← عضو اولش (رفتار قبلی Visa)؛ خالی ← یک ردیف null.
     *
     * @return array<int, string|null>
     */
    private function subjects(mixed $goal): array
    {
        $goals = is_array($goal) ? array_values($goal) : [$goal];

        if ($goals === []) {
            return [null];
        }

        return array_map(function ($item) {
            if (is_array($item)) {
                $item = $item[array_key_first($item)] ?? null;
            }

            return is_scalar($item) && $item !== '' && $item !== false ? mb_substr((string) $item, 0, 64) : null;
        }, $goals);
    }
}
