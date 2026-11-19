<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Services\SystemLog\Application\Services\AccessService;
use App\Services\SystemLog\Application\Services\ErrorService;
use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Presentation\Http\Requests\ListErrorsRequest;
use App\Services\SystemLog\Presentation\Http\Requests\UpdateErrorStatusRequest;
use App\Services\SystemLog\Presentation\Http\Resources\ErrorResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * لیست/جزئیات/تغییر وضعیت خطاها. همه‌ی شعبه‌ها، ولی فقط در دسته‌های دسترسی اپراتور (AccessService::requireScope).
 * هر کسی که خطایی را می‌بیند وضعیتش را هم می‌تواند عوض کند.
 */
class ErrorController extends SystemLogController
{
    public function __construct(
        private readonly ErrorService $errors,
        private readonly AccessService $access,
    ) {}

    public function index(ListErrorsRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $scope = $this->access->requireScope($this->operatorId($request), $this->branchId($request));
            $data = $request->validated();

            $filters = array_filter([
                'status' => isset($data['status']) ? $this->status($data['status'])->value : null,
                'source' => $data['source_id'] ?? null,
                'type' => $data['type_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'from' => isset($data['from']) ? Carbon::parse($data['from']) : null,
                'to' => isset($data['to']) ? Carbon::parse($data['to']) : null,
                'text' => $data['text'] ?? null,
            ], fn ($v) => $v !== null);

            return $this->page($this->errors->search($filters, $scope, (int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 20)), ErrorResource::class);
        });
    }

    public function show(Request $request, string $ref): JsonResponse
    {
        return $this->guard(function () use ($request, $ref) {
            $scope = $this->access->requireScope($this->operatorId($request), $this->branchId($request));
            $error = $this->errors->find($ref, $scope);

            return $error === null ? $this->notFound() : $this->payload((new ErrorResource($error))->resolve());
        });
    }

    public function updateStatus(UpdateErrorStatusRequest $request, string $ref): JsonResponse
    {
        return $this->guard(function () use ($request, $ref) {
            $operatorId = $this->operatorId($request);
            $scope = $this->access->requireScope($operatorId, $this->branchId($request));

            if (! $this->errors->updateStatus($scope, $ref, $this->status($request->validated('status')), $request->validated('note'), $operatorId)) {
                return $this->notFound();
            }

            return $this->payload((new ErrorResource($this->errors->find($ref, $scope)))->resolve());
        });
    }

    /** نام وضعیت (اعتبارسنجی‌شده در Request) ← enum */
    private function status(string $key): ErrorStatus
    {
        return collect(ErrorStatus::cases())->first(fn (ErrorStatus $s) => $s->key() === $key);
    }
}
