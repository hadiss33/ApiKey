<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Stores;

use App\Services\SystemLog\Application\Contracts\AuditStore;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Audit\AuditContext;
use App\Services\SystemLog\Infrastructure\Persistence\Models\LogType;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemLog;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemLogDetail;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final class EloquentAuditStore implements AuditStore
{
    /**
     * کش نوع‌ها در همین پروسه (کد ← شناسه)؛ ثبت در ورکر طولانی‌عمر انجام می‌شود، پس یافتن نوع یک بار به‌ازای هر نوع است.
     *
     * @var array<string, int>
     */
    private static array $typeIds = [];

    public function resolveType(string $code): int
    {
        if (isset(self::$typeIds[$code])) {
            return self::$typeIds[$code];
        }

        $type = LogType::query()->firstWhere('code', $code);

        if ($type === null) {
            // createOrFirst در رقابت دو پروسه روی کد یکتا امن است
            $type = LogType::query()->createOrFirst(['code' => $code], ['category' => (string) config('system_log.audit.default_category', 'other')]);
            Log::warning('SystemLog: unknown audit log type registered; set its category and title from the panel', ['code' => $code]);
        }

        return self::$typeIds[$code] = $type->id;
    }

    public function store(int $typeId, ?string $subjectId, AuditContext $context, ?array $details): int
    {
        return (new SystemLog)->getConnection()->transaction(function () use ($typeId, $subjectId, $context, $details) {
            $log = SystemLog::query()->create([
                'branch_id' => $context->branchId,
                'operator_id' => $context->operatorId,
                'type_id' => $typeId,
                'subject_id' => $subjectId,
                'ip' => $context->ip,
                'user_agent' => $context->userAgent,
                // زمان وقوع (نه لحظه‌ی اجرای جاب)، با میلی‌ثانیه
                'created_at' => $context->occurredAt->format('Y-m-d H:i:s.v'),
            ]);

            if ($details !== null) {
                SystemLogDetail::query()->create(['log_id' => $log->id, 'details' => $details]);
            }

            return $log->id;
        });
    }

    public function history(string $typeOrCategory, string $subjectId, int $branchId, ?DateTimeInterface $from, ?DateTimeInterface $to, int $limit): array
    {
        $typeIds = LogType::query()->where('code', $typeOrCategory)->orWhere('category', $typeOrCategory)->pluck('id')->all();

        if ($typeIds === []) {
            return [];
        }

        return SystemLog::query()->withDisplay()
            ->where('branch_id', $branchId)
            ->where('subject_id', $subjectId)
            ->whereIn('type_id', $typeIds)
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from->format('Y-m-d H:i:s') . '.000'))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to->format('Y-m-d H:i:s') . '.999'))
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (SystemLog $log) => $log->toRecord())
            ->all();
    }

    public function search(array $filters, int $branchId, int $page, int $perPage): Page
    {
        $query = SystemLog::query()->withDisplay()->where('branch_id', $branchId);

        foreach (['subject_id', 'operator_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        $query
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->whereHas('type', fn (Builder $t) => $t->where('code', $v)))
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->whereHas('type', fn (Builder $t) => $t->where('category', $v)))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', $v->format('Y-m-d H:i:s') . '.000'))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', $v->format('Y-m-d H:i:s') . '.999'));

        $total = (clone $query)->count();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $items = $query->orderByDesc('id')->forPage($page, $perPage)->get()->map(fn (SystemLog $log) => $log->toRecord())->all();

        return new Page($items, $total, $page, $perPage);
    }

    public function categories(): array
    {
        return LogType::query()->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function types(): array
    {
        return LogType::query()->orderBy('code')->get()->map(fn (LogType $type) => $type->toSummary())->all();
    }

    public function updateType(int $id, ?string $category, ?string $titleFa, ?string $titleEn): bool
    {
        $type = LogType::query()->find($id);

        if ($type === null) {
            return false;
        }

        $type->fill(array_filter(['category' => $category, 'title_fa' => $titleFa, 'title_en' => $titleEn], fn ($v) => $v !== null))->save();

        return true;
    }
}
