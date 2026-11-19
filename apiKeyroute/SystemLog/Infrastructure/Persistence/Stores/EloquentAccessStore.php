<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Stores;

use App\Models\Official\Operators\Operator;
use App\Services\SystemLog\Application\Contracts\AccessStore;
use App\Services\SystemLog\Domain\Access\AccessOperator;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Domain\Access\Grant;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemLogAccess;

/** اپراتور فقط خوانده می‌شود (مدل Operator پروژه)؛ دسترسی‌ها در system_log_access و دسته‌هایشان در جدول واسط. */
final class EloquentAccessStore implements AccessStore
{
    private const OPERATOR_COLUMNS = ['id', 'is_admin', 'status', 'branch', 'mobile'];

    public function operator(int $id): ?AccessOperator
    {
        $operator = Operator::query()->select(self::OPERATOR_COLUMNS)->find($id);

        return $operator ? $this->operatorRef($operator) : null;
    }

    public function grant(int $operatorId): ?Grant
    {
        return SystemLogAccess::query()->with('categories')->where('operator_id', $operatorId)->first()?->toGrant();
    }

    public function grants(): array
    {
        return SystemLogAccess::query()->with('categories')->orderBy('id')->get()->map(fn (SystemLogAccess $row) => $row->toGrant())->all();
    }

    public function save(int $operatorId, bool $isManager, bool $isActive, array $categoryIds, ?AlertChannel $channel, int $actorId): Grant
    {
        return (new SystemLogAccess)->getConnection()->transaction(function () use ($operatorId, $isManager, $isActive, $categoryIds, $channel, $actorId) {
            $row = SystemLogAccess::query()->firstOrNew(['operator_id' => $operatorId]);

            if (! $row->exists) {
                $row->created_by = $actorId;
            }

            $row->fill(['is_manager' => $isManager, 'is_active' => $isActive, 'channel' => $channel, 'updated_by' => $actorId])->save();
            $row->categories()->sync($categoryIds);

            return $row->load('categories')->toGrant();
        });
    }

    public function delete(int $operatorId): bool
    {
        // ردیف‌های جدول واسط با FK cascade حذف می‌شوند
        return SystemLogAccess::query()->where('operator_id', $operatorId)->delete() > 0;
    }

    public function activeGrants(): array
    {
        $rows = SystemLogAccess::query()->with('categories')->where('is_active', true)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $operators = Operator::query()->select(self::OPERATOR_COLUMNS)
            ->whereIn('id', $rows->pluck('operator_id')->all())->where('status', 1)->get()->keyBy('id');

        $result = [];

        foreach ($rows as $row) {
            if ($operator = $operators->get($row->operator_id)) {
                $result[] = [$row->toGrant(), $this->operatorRef($operator)];
            }
        }

        return $result;
    }

    private function operatorRef(Operator $operator): AccessOperator
    {
        $mobile = trim((string) $operator->mobile);

        return new AccessOperator(
            id: (int) $operator->id,
            isAdmin: (bool) $operator->is_admin,
            isActive: (int) $operator->status === 1,
            branches: $this->branches($operator->branch),
            mobile: $mobile !== '' ? $mobile : null,
        );
    }

    /**
     * ستون branch آرایه‌ی JSON شعبه‌هاست؛ [0] یعنی «همه‌ی شعبه‌ها» ← null. خالی/نامعتبر ← هیچ شعبه.
     *
     * @return array<int, int>|null
     */
    private function branches(mixed $value): ?array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($decoded, 'is_numeric'))));

        return in_array(0, $ids, true) ? null : $ids;
    }
}
