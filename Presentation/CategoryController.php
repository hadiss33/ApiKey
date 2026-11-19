<?php

namespace App\Services\ApiKey\Presentation;

use App\Http\Controllers\Controller;
use App\Jobs\SystemLog;
use App\Services\ApiKey\Application\Services\ApiKeyService;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * پنل دسته‌های کلید API (api/v2/api-keys/categories). فقط اپراتور ادمین «شعبه‌ی request خودش».
 * هر دسته چند قانون (prefix + سطح) دارد؛ کلیدها فقط به دسته وصل می‌شوند، نه مستقیم به prefix.
 * ویرایش دسته روی همه‌ی کلیدهای وصل‌شده اثر می‌گذارد: تعداد متأثر برگردانده، کش آن‌ها پاک و در لاگ فعالیت ثبت می‌شود.
 */
class CategoryController extends Controller
{
    use PanelGuard;

    public function __construct(
        private readonly CategoryRegistry $categories,
        private readonly ApiKeyService $keys,
    ) {}

    /** درخت prefix ها با شمارنده‌ی read/write هر گره (برای انتخاب در پنل) */
    public function tree(Request $request): JsonResponse
    {
        return $this->guard($request, fn() => $this->payload([
            'depth' => (int) config('api_key.route_tree.depth', 3),
            'items' => $this->categories->tree(),
        ]));
    }

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
            $category = $this->categories->findForBranch($id, $branch) ?? throw ApiKeyException::notFound();

            return $this->payload($category + ['key_count' => $this->categories->keyCount($id)]);
        });
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        return $this->guard($request, function (int $branch, int $operator) use ($request) {
            $category = $this->categories->create($branch, $request->validated('name'), $request->validated('rules'), $operator);
            $this->audit('ApiKeyCategoryCreated', $category);

            return $this->payload($category, 201);
        });
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch) use ($request, $id) {
            $category = $this->categories->update($id, $branch, $request->validated());
            $affected = $this->categories->keyCount($id);

            $this->keys->forgetCategory($id); // تغییر روی کلیدهای وصل‌شده فوراً اعمال شود
            $this->audit('ApiKeyCategoryUpdated', $category, ['affected_keys' => $affected]);

            return $this->payload($category + ['affected_keys' => $affected]);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->guard($request, function (int $branch) use ($id) {
            $this->categories->delete($id, $branch); // با کلید وصل‌شده ← ۴۲۲
            $this->audit('ApiKeyCategoryDeleted', ['id' => $id]);

            return $this->payload(['id' => $id, 'deleted' => true]);
        });
    }

    /** لاگ فعالیت؛ شکستش عملیات را برنمی‌گرداند */
    private function audit(string $type, array $category, array $extra = []): void
    {
        try {
            SystemLog::dispatch([
                'type' => $type,
                'goal' => $category['id'] ?? null,
                'data' => array_filter([
                    'name' => $category['name'] ?? null,
                    'is_active' => $category['is_active'] ?? null,
                    'rules' => $category['rules'] ?? null,
                ], fn($v) => $v !== null) + $extra,
            ]);
        } catch (Throwable $e) {
            logger()->warning('ApiKey: category audit dispatch failed', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }
}
