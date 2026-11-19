<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Access\AccessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

/**
 * پایه‌ی کنترلرهای پنل SystemLog: قالب خروجی items | payload | error | empty (+ meta.timestamp)
 * و تبدیل خطاها به error بدون نشت trace/file. اپراتور فقط از attribute میدلور خوانده می‌شود.
 */
abstract class SystemLogController extends Controller
{
    protected function operatorId(Request $request): int
    {
        $operator = $request->attributes->get('operator');

        return (int) (is_object($operator) ? $operator->id : ($operator['id'] ?? 0));
    }

    /** شعبه‌ی request (از میدلور دامنه)؛ ممکن است null باشد */
    protected function branchId(Request $request): ?int
    {
        $branch = $request->attributes->get('branch');

        return is_numeric($branch) ? (int) $branch : null;
    }

    /** فقط کاربر «اپراتور» (گروه توکن base) و با شعبه‌ی مشخص؛ شعبه را برمی‌گرداند */
    protected function operatorBranch(Request $request): int
    {
        $branch = $this->branchId($request);

        if ($request->attributes->get('group') !== 'base' || $branch === null) {
            throw AccessException::forbidden('system_log.access.denied');
        }

        return $branch;
    }

    /** @param  callable(): JsonResponse  $callback */
    protected function guard(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (AccessException $e) {
            return $this->error($e->forbidden ? 4030 : 4220, __($e->getMessage()), $e->forbidden ? 403 : 422);
        } catch (Throwable $e) {
            logger()->error('SystemLog: unexpected panel error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return $this->error(5000, __('system_log.api.server_error'), 500, app()->environment('local')
                ? ['debug' => ['file' => $e->getFile(), 'line' => $e->getLine(), 'exception' => $e->getMessage()]]
                : []);
        }
    }

    protected function payload(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(['payload' => $payload, 'empty' => false, 'meta' => ['timestamp' => time()]], $status);
    }

    /** @param  class-string<JsonResource>  $resource */
    protected function items(iterable $items, string $resource): JsonResponse
    {
        $data = $resource::collection($items)->resolve();

        return response()->json(['items' => $data, 'empty' => $data === [], 'meta' => ['timestamp' => time()]]);
    }

    /** @param  class-string<JsonResource>  $resource */
    protected function page(Page $page, string $resource): JsonResponse
    {
        return response()->json([
            'items' => $resource::collection($page->items)->resolve(),
            'empty' => $page->items === [],
            'meta' => [
                'current_page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
                'timestamp' => time(),
            ],
        ]);
    }

    protected function notFound(): JsonResponse
    {
        return $this->error(4040, __('system_log.api.not_found'), 404);
    }

    protected function error(int $code, string $message, int $status, array $meta = []): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message], 'meta' => ['timestamp' => time()] + $meta], $status);
    }
}
