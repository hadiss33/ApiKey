<?php

namespace App\Services\ApiKey\Presentation;

use App\Http\Controllers\Controller;
use App\Jobs\SystemLog;
use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Application\Services\ApiKeyService;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

/**
 * پنل کلیدهای API (api/v2/api-keys). فقط اپراتور (گروه base) با is_admin، و فقط کلیدهای «شعبه‌ی request خودش».
 * شعبه و اپراتور فقط از attribute میدلورها خوانده می‌شوند. خروجی: items | payload | error | empty (+ meta.timestamp).
 * عملیات نوشتن در لاگ فعالیت SystemLog ثبت می‌شود (بدون کلید خام).
 */
class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly CategoryRegistry $categories,
    ) {}

    /** کاتالوگ دسته‌ها (گروه‌بندی‌شده، با prefix و سطح‌های هر دسته) و قالب‌ها، از config */
    public function categories(Request $request): JsonResponse
    {
        return $this->guard($request, fn () => $this->payload(JsonResource::make($this->categories->catalog())->resolve()));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->guard($request, function (int $branch) {
            $data = ApiKeyResource::collection($this->keys->list($branch))->resolve();

            return response()->json(['items' => $data, 'empty' => $data === [], 'meta' => ['timestamp' => time()]]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->guard($request, fn (int $branch) => $this->key($this->keys->find($branch, $id)));
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

    // ───────────────────────── کمکی ─────────────────────────

    /** @param  callable(int $branch, int $operator): JsonResponse  $callback */
    private function guard(Request $request, callable $callback): JsonResponse
    {
        try {
            $operator = $request->attributes->get('operator');
            $branch = $request->attributes->get('branch');

            if ($request->attributes->get('group') !== 'base' || ! is_object($operator)
                || (int) ($operator->is_admin ?? 0) !== 1 || ! is_numeric($branch)) {
                throw ApiKeyException::forbidden();
            }

            return $callback((int) $branch, (int) $operator->id);
        } catch (ApiKeyException $e) {
            return response()->json([
                'error' => array_filter([
                    'code' => $e->errorCode,
                    'message' => __($e->getMessage(), $e->replace),
                    'fields' => $e->fields === [] ? null : array_map(
                        fn (array $keys) => array_map(fn ($k) => __($k, $e->replace), $keys),
                        $e->fields,
                    ),
                ], fn ($v) => $v !== null),
                'meta' => ['timestamp' => time()],
            ], $e->status);
        } catch (Throwable $e) {
            logger()->error('ApiKey: unexpected panel error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            report($e);

            return response()->json([
                'error' => ['code' => 5000, 'message' => __('api_key.errors.server_error')],
                'meta' => ['timestamp' => time()] + (app()->environment('local')
                    ? ['debug' => ['file' => $e->getFile(), 'line' => $e->getLine(), 'exception' => $e->getMessage()]]
                    : []),
            ], 500);
        }
    }

    private function key(ApiKeyRecord $record, int $status = 200): JsonResponse
    {
        return $this->payload((new ApiKeyResource($record))->resolve(), $status);
    }

    private function payload(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(['payload' => $payload, 'empty' => false, 'meta' => ['timestamp' => time()]], $status);
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
                    'grants' => $this->categories->grantKeys($record->grants),
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
