<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Services\SystemLog\Application\Services\AccessService;
use App\Services\SystemLog\Application\Services\CategoryService;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;
use App\Services\SystemLog\Presentation\Http\Requests\SaveCategoryRequest;
use App\Services\SystemLog\Presentation\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** دسته‌های خطا: لیست (برای فیلتر و فرم) برای هر کسی که خطا می‌بیند؛ ساخت و ویرایش فقط مدیر. */
class CategoryController extends SystemLogController
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly AccessService $access,
    ) {}

    /** مدیر همه را می‌بیند (با «all» برای فرم دسترسی)؛ بقیه فقط دسته‌های خودشان (برای فیلتر لیست) */
    public function index(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $scope = $this->access->requireScope($this->operatorId($request), $this->branchId($request));

            $rows = array_filter($this->categories->taxonomy()->all(), fn (array $row) => match (true) {
                $scope->isManager => true,
                $row['kind'] === ErrorTaxonomy::ALL => false,
                default => $scope->all || in_array($row['id'], $scope->categoryIds, true),
            });

            return $this->items(array_values($rows), CategoryResource::class);
        });
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->access->requireManager($this->operatorId($request), $this->branchId($request));
            $d = $request->validated();

            return $this->payload((new CategoryResource($this->categories->create($d['kind'], $d['key'], $d['title_fa'] ?? null, $d['title_en'] ?? null, $d['classes'] ?? null)))->resolve(), 201);
        });
    }

    public function update(SaveCategoryRequest $request, int $id): JsonResponse
    {
        return $this->guard(function () use ($request, $id) {
            $this->access->requireManager($this->operatorId($request), $this->branchId($request));
            $d = $request->validated();

            if (! $this->categories->update($id, $d['title_fa'] ?? null, $d['title_en'] ?? null, $d['classes'] ?? null)) {
                return $this->notFound();
            }

            return $this->items($this->categories->taxonomy()->all(), CategoryResource::class);
        });
    }
}
