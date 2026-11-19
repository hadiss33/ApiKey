<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Services\SystemLog\Application\Services\AccessService;
use App\Services\SystemLog\Application\Services\AuditService;
use App\Services\SystemLog\Presentation\Http\Requests\ListAuditLogsRequest;
use App\Services\SystemLog\Presentation\Http\Requests\UpdateTypeRequest;
use App\Services\SystemLog\Presentation\Http\Resources\AuditLogResource;
use App\Services\SystemLog\Presentation\Http\Resources\TypeResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * لاگ فعالیت: هر اپراتور فقط لاگ‌های «شعبه‌ی request خودش» را می‌بیند (بدون جدول دسترسی؛ b2c/colleague/website چیزی نمی‌بینند).
 * «نوع»های رویداد: دیدن برای هر اپراتور، تعیین دسته و نام فقط برای مدیر.
 */
class AuditLogController extends SystemLogController
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AccessService $access,
    ) {}

    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $branch = $this->operatorBranch($request);
            $data = $request->validated();

            $filters = array_filter([
                'type' => $data['type'] ?? null,
                'category' => $data['category'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
                'from' => isset($data['from']) ? Carbon::parse($data['from']) : null,
                'to' => isset($data['to']) ? Carbon::parse($data['to']) : null,
            ], fn ($v) => $v !== null);

            return $this->page($this->audit->search($filters, $branch, (int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 20)), AuditLogResource::class);
        });
    }

    public function types(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->operatorBranch($request);

            return $this->items($this->audit->types(), TypeResource::class);
        });
    }

    public function updateType(UpdateTypeRequest $request, int $id): JsonResponse
    {
        return $this->guard(function () use ($request, $id) {
            $this->access->requireManager($this->operatorId($request), $this->branchId($request));
            $data = $request->validated();

            if (! $this->audit->updateType($id, $data['category'] ?? null, $data['title_fa'] ?? null, $data['title_en'] ?? null)) {
                return $this->notFound();
            }

            return $this->items($this->audit->types(), TypeResource::class);
        });
    }
}
