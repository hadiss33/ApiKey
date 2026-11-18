<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\CategoryStore;
use App\Services\ApiKey\Infrastructure\Models\ApiKeyCategory;
use App\Services\ApiKey\Infrastructure\Models\ApiKeyCategoryKey;
use App\Services\ApiKey\Infrastructure\Models\ApiKeyCategoryRule;

/** پیاده‌سازی CategoryStore با Eloquent. */
final class EloquentCategoryStore implements CategoryStore
{
    public function listForBranch(int $branchId): array
    {
        return ApiKeyCategory::query()
            ->with('rules')
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiKeyCategory $c) => $this->toArray($c))
            ->all();
    }

    public function find(int $id, int $branchId): ?array
    {
        $cat = ApiKeyCategory::query()->with('rules')->where('branch_id', $branchId)->find($id);

        return $cat ? $this->toArray($cat) : null;
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ApiKeyCategory::query()
            ->with('rules')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (ApiKeyCategory $c) => $this->toArray($c))
            ->keyBy('id')
            ->all();
    }

    public function create(int $branchId, string $name, array $rules, int $operatorId): array
    {
        return (new ApiKeyCategory)->getConnection()->transaction(function () use ($branchId, $name, $rules, $operatorId) {
            $cat = ApiKeyCategory::query()->create([
                'branch_id' => $branchId,
                'name' => $name,
                'is_active' => true,
                'created_by' => $operatorId,
            ]);

            $this->syncRules($cat->id, $rules);

            return $this->toArray($cat->load('rules'));
        });
    }

    public function update(int $id, int $branchId, array $data): array
    {
        return (new ApiKeyCategory)->getConnection()->transaction(function () use ($id, $branchId, $data) {
            /** @var ApiKeyCategory $cat */
            $cat = ApiKeyCategory::query()->where('branch_id', $branchId)->lockForUpdate()->findOrFail($id);

            $attributes = array_filter([
                'name' => isset($data['name']) ? (string) $data['name'] : null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : null,
            ], fn ($v) => $v !== null);

            if ($attributes !== []) {
                $cat->fill($attributes)->save();
            }

            if (isset($data['rules'])) {
                $this->syncRules($cat->id, $data['rules']);
            }

            return $this->toArray($cat->load('rules'));
        });
    }

    public function hasKeys(int $id): bool
    {
        return ApiKeyCategoryKey::query()->where('category_id', $id)->exists();
    }

    public function keyCount(int $id): int
    {
        return ApiKeyCategoryKey::query()->where('category_id', $id)->count();
    }

    // ─────────────────────── کمکی ───────────────────────

    private function toArray(ApiKeyCategory $cat): array
    {
        return [
            'id' => (int) $cat->id,
            'branch_id' => (int) $cat->branch_id,
            'name' => (string) $cat->name,
            'is_active' => (bool) $cat->is_active,
            'rules' => $cat->rules
                ->map(fn (ApiKeyCategoryRule $r) => ['prefix' => $r->prefix, 'level' => $r->level])
                ->sortBy('prefix')
                ->values()
                ->all(),
        ];
    }

    /** @param  array<int, array{prefix:string, level:string}>  $rules */
    private function syncRules(int $categoryId, array $rules): void
    {
        ApiKeyCategoryRule::query()->where('category_id', $categoryId)->delete();

        $rows = [];

        foreach ($rules as $rule) {
            $prefix = trim((string) ($rule['prefix'] ?? ''), '/');
            $level = (string) ($rule['level'] ?? 'read');

            if ($prefix !== '' && in_array($level, ['read', 'write'], true)) {
                $rows[$prefix] = ['category_id' => $categoryId, 'prefix' => $prefix, 'level' => $level];
            }
        }

        if ($rows !== []) {
            ApiKeyCategoryRule::query()->insert(array_values($rows));
        }
    }
}
