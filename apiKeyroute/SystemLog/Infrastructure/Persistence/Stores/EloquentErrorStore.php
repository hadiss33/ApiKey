<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Stores;

use App\Services\SystemLog\Application\Contracts\ErrorStore;
use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Access\AccessScope;
use App\Services\SystemLog\Domain\Error\ErrorEntry;
use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

final class EloquentErrorStore implements ErrorStore
{
    public function record(ErrorEntry $entry, string $fingerprint, bool $refreshSnapshot = true): ErrorRecord
    {
        $at = $entry->occurredAt->format('Y-m-d H:i:s');

        // عکس وقوع: هنگام ساخت همیشه؛ هنگام تکرار فقط با $refreshSnapshot (ادغام N ساعته: عکس اولین وقوع می‌ماند)
        $snapshot = [
            'source' => $entry->source,
            'location' => $entry->location,
            'status_code' => $entry->statusCode,
            'actor_type' => $entry->actorType,
            'actor_id' => $entry->actorId,
            'ip' => $entry->ip,
            'stack' => $entry->stack,
            'details' => $entry->details === null
                ? null
                : json_encode($entry->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ];

        // هویت خطا (فقط با اولین وقوع): هنگام تکرار به‌روز نمی‌شود
        $row = [
            'branch_id' => $entry->branchId,
            'fingerprint' => $fingerprint,
            'exception_class' => $entry->exceptionClass,
            'type' => $entry->type,
            'level' => $entry->level->value,
            'code' => $entry->code,
            'message' => $entry->message,
            'file' => $entry->file,
            'line' => $entry->line,
            'status' => ErrorStatus::New->value,
            'occurrences' => 1,
            'created_at' => $at,
            'last_seen_at' => $at,
        ] + $snapshot;

        $resolved = ErrorStatus::Resolved->value;
        $new = ErrorStatus::New->value;

        // ترتیب مهم است: تخصیص‌های ON DUPLICATE KEY UPDATE از چپ به راست اجرا می‌شوند و مقدار «به‌روزشده»ی ستون‌های قبلی
        // را می‌بینند؛ پس resolved_* و notified_at باید قبل از status بیایند. برگشت خطای resolved: رسیدگی پاک و status = new.
        $update = [
            'resolved_by' => new Expression("IF(status = {$resolved}, NULL, resolved_by)"),
            'resolved_at' => new Expression("IF(status = {$resolved}, NULL, resolved_at)"),
            'notified_at' => new Expression("IF(status = {$resolved}, NULL, notified_at)"),
            'status' => new Expression("IF(status = {$resolved}, {$new}, status)"),
            'occurrences' => new Expression('occurrences + 1'),
            'last_seen_at' => $at,
        ] + ($refreshSnapshot ? $snapshot : []);

        SystemError::query()->upsert([$row], ['fingerprint'], $update);

        return SystemError::query()->where('fingerprint', $fingerprint)->first(SystemError::LIST_COLUMNS)->toRecord();
    }

    public function find(int $id, ?AccessScope $scope = null): ?ErrorRecord
    {
        $query = SystemError::query();

        if ($scope !== null) {
            $this->applyScope($query, $scope);
        }

        return $query->whereKey($id)->first()?->toRecord(full: true);
    }

    public function search(array $filters, AccessScope $scope, int $page, int $perPage): Page
    {
        $query = $this->applyScope(SystemError::query(), $scope);

        foreach (['status', 'source', 'type', 'branch_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        $query
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('last_seen_at', '>=', $v->format('Y-m-d H:i:s')))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('last_seen_at', '<=', $v->format('Y-m-d H:i:s')))
            ->when($filters['text'] ?? null, fn (Builder $q, $v) => $q->where('message', 'like', '%' . addcslashes((string) $v, '\\%_') . '%'));

        $total = (clone $query)->count();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $items = $query->orderByDesc('last_seen_at')->orderByDesc('id')->forPage($page, $perPage)
            ->get(SystemError::LIST_COLUMNS)->map(fn (SystemError $m) => $m->toRecord())->all();

        return new Page($items, $total, $page, $perPage);
    }

    public function updateStatus(int $id, ErrorStatus $status, ?string $note, ?int $operatorId): bool
    {
        $error = SystemError::query()->find($id, ['id']);

        if ($error === null) {
            return false;
        }

        $resolved = $status === ErrorStatus::Resolved;

        $error->fill(array_filter([
            'status' => $status->value,
            'resolved_by' => $resolved ? $operatorId : null,
            'resolved_at' => $resolved ? now() : null,
            'note' => $note === null ? null : mb_substr($note, 0, 1000),
        ], fn ($v, $k) => $v !== null || in_array($k, ['resolved_by', 'resolved_at'], true), ARRAY_FILTER_USE_BOTH))->save();

        return true;
    }

    public function claimNotification(int $id): bool
    {
        return SystemError::query()->whereKey($id)->whereNull('notified_at')->update(['notified_at' => now()]) === 1;
    }

    public function pruneResolved(\DateTimeInterface $before, bool $dryRun = false): int
    {
        $query = fn () => SystemError::query()->where('status', ErrorStatus::Resolved->value)->where('resolved_at', '<', $before->format('Y-m-d H:i:s'));

        if ($dryRun) {
            return $query()->count();
        }

        $total = 0;
        $chunk = 5000;

        do {
            $deleted = $query()->orderBy('id')->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted >= $chunk);

        return $total;
    }

    /** محدوده‌ی دسترسی: همه‌ی شعبه‌ها، ولی فقط خطاهایی که منبع «یا» نوعشان در دسته‌های کاربر است (لیست خالی = هیچ). */
    private function applyScope(Builder $query, AccessScope $scope): Builder
    {
        if (! $scope->all) {
            $query->where(fn (Builder $q) => $q->whereIn('source', $scope->categoryIds)->orWhereIn('type', $scope->categoryIds));
        }

        return $query;
    }
}
