<?php

namespace App\Services\ApiKey\Presentation;

use App\Http\Controllers\Controller;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * پنل دسته‌های کلید API (api/v2/api-keys/categories). فقط اپراتور ادمین «شعبه‌ی request خودش».
 * هر دسته چند قانون (prefix + سطح) دارد؛ کلیدها فقط به دسته وصل می‌شوند، نه مستقیم به prefix.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRegistry $categories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->guard($request, function (int $branch) {
            $data = $this->categories->listForBranch($branch);

            return response()->json(['items' => $data, 'empty' => $data === [], 'meta' => ['timestamp' => time()]]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch) use ($id) {
            $category = $this->categories->findForBranch($id, $branch);

            if ($category === null) {
                throw ApiKeyException::notFound();
            }

            return $this->payload($category);
        });
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($request) {
            $category = $this->categories->create($branch, $request->validated('name'), $request->validated('rules'), $operator);

            return $this->payload($category, 201);
        });
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch) use ($request, $id) {
            $category = $this->categories->update($id, $branch, $request->validated());

            return $this->payload($category);
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
            logger()->error('ApiKey: unexpected category panel error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            report($e);

            return response()->json([
                'error' => ['code' => 5000, 'message' => __('api_key.errors.server_error')],
                'meta' => ['timestamp' => time()] + (app()->environment('local')
                    ? ['debug' => ['file' => $e->getFile(), 'line' => $e->getLine(), 'exception' => $e->getMessage()]]
                    : []),
            ], 500);
        }
    }

    private function payload(array $category, int $status = 200): JsonResponse
    {
        return response()->json(['payload' => $category, 'empty' => false, 'meta' => ['timestamp' => time()]], $status);
    }
}
