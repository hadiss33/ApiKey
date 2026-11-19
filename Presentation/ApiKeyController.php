<?php

namespace App\Services\ApiKey\Presentation;

use App\Http\Controllers\Controller;
use App\Jobs\SystemLog;
use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Application\Services\ApiKeyService;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * پنل کلیدهای API (api/v2/api-keys). فقط اپراتور (گروه base) با is_admin، و فقط کلیدهای «شعبه‌ی request خودش».
 * شعبه و اپراتور فقط از attribute میدلورها خوانده می‌شوند. خروجی: items | payload | error | empty (+ meta.timestamp).
 * عملیات نوشتن در لاگ فعالیت SystemLog ثبت می‌شود (بدون کلید خام).
 * کاتالوگ دسته‌ها دیگر اینجا نیست: CategoryController (categories و categories/tree).
 */
class ApiKeyController extends Controller
{
    use PanelGuard;

    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly CategoryRegistry $categories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->guard($request, function (int $branch) {
            $data = ApiKeyResource::collection($this->keys->list($branch))->resolve();

            return response()->json(['items' => $data, 'empty' => $data === [], 'meta' => ['timestamp' => time()]]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->guard($request, fn(int $branch) => $this->key($this->keys->find($branch, $id)));
    }

    public function store(ApiKeyRequest $request): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($request) {
            $record = $this->keys->issue($branch, $operator, $request->validated());
            $this->audit('ApiKeyCreated', $record);

            return $this->key($record, 201);
        });
    }

    public function update(ApiKeyRequest $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($request, $id) {
            $record = $this->keys->update($branch, $id, $operator, $request->validated());
            $this->audit('ApiKeyUpdated', $record);

            return $this->key($record);
        });
    }

    public function rotate(ApiKeyRequest $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($request, $id) {
            $grace = $request->validated('grace_hours');
            $record = $this->keys->rotate($branch, $id, $operator, $grace === null ? null : (int) $grace);
            $this->audit('ApiKeyRotated', $record);

            return $this->key($record);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($id) {
            $record = $this->keys->revoke($branch, $id, $operator);
            $this->audit('ApiKeyRevoked', $record);

            return $this->key($record);
        });
    }

    private function key(ApiKeyRecord $record, int $status = 200): JsonResponse
    {
        return $this->payload((new ApiKeyResource($record))->resolve(), $status);
    }

    /** لاگ فعالیت؛ شکستش عملیات را برنمی‌گرداند. کلید خام و hash هرگز در لاگ نمی‌روند. */
    private function audit(string $type, ApiKeyRecord $record): void
    {
        try {
            SystemLog::dispatch([
                'type' => $type,
                'goal' => $record->id,
                'data' => [
                    'name' => $record->name,
                    'prefix' => $record->prefix,
                    'categories' => $record->categoryIds,
                    'effective_access' => $this->categories->effectiveAccess($record->categoryIds),
                    'allowed_ips' => $record->allowedIps,
                    'origins' => $record->origins,
                    'expires_at' => $record->expiresAt?->format(DATE_ATOM),
                    'status' => $record->status(),
                ],
            ]);
        } catch (Throwable $e) {
            logger()->warning('ApiKey: audit log dispatch failed', ['type' => $type, 'api_key_id' => $record->id, 'error' => $e->getMessage()]);
        }
    }
}
