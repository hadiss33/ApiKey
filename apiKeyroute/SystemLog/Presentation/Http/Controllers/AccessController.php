<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Services\SystemLog\Application\Services\AccessService;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Presentation\Http\Requests\SaveAccessRequest;
use App\Services\SystemLog\Presentation\Http\Resources\GrantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** دسترسی به خطاها: فهرست و تنظیم فقط مدیر؛ «me» برای هر اپراتور (ساخت منوی پنل). */
class AccessController extends SystemLogController
{
    public function __construct(private readonly AccessService $access) {}

    /** آیا من خطا می‌بینم؟ آیا مدیرم؟ */
    public function me(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $scope = $this->access->scopeFor($this->operatorId($request), $this->branchId($request));

            return $this->payload([
                'can_view_errors' => $scope !== null,
                'is_manager' => $scope?->isManager ?? false,
                'all_categories' => $scope?->all ?? false,
            ]);
        });
    }

    public function index(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->items($this->access->grants($this->operatorId($request), $this->branchId($request)), GrantResource::class));
    }

    public function update(SaveAccessRequest $request, int $operatorId): JsonResponse
    {
        return $this->guard(function () use ($request, $operatorId) {
            $d = $request->validated();

            $grant = $this->access->save(
                managerId: $this->operatorId($request),
                branchId: $this->branchId($request),
                operatorId: $operatorId,
                isManager: (bool) ($d['is_manager'] ?? false),
                isActive: (bool) ($d['is_active'] ?? true),
                categoryIds: array_map('intval', $d['category_ids'] ?? []),
                channel: AlertChannel::tryFrom((string) ($d['channel'] ?? '')),
            );

            return $this->payload((new GrantResource($grant))->resolve());
        });
    }

    public function destroy(Request $request, int $operatorId): JsonResponse
    {
        return $this->guard(fn () => $this->access->revoke($this->operatorId($request), $this->branchId($request), $operatorId)
            ? $this->payload(['deleted' => true])
            : $this->notFound());
    }
}
