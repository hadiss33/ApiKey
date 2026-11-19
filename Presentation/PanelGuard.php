<?php

namespace App\Services\ApiKey\Presentation;

use App\Services\ApiKey\Domain\ApiKeyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** گارد مشترک پنل: فقط اپراتور ادمین (گروه base) و فقط شعبه‌ی request خودش؛ خطاها با قالب ثابت ماژول. */
trait PanelGuard
{
    /** @param  callable(int $branch, int $operator): JsonResponse  $callback */
    private function guard(Request $request, callable $callback): JsonResponse
    {
        try {
            $operator = $request->attributes->get('operator');
            $branch = $request->attributes->get('branch');

            if (
                $request->attributes->get('group') !== 'base' || ! is_object($operator)
                || (int) ($operator->is_admin ?? 0) !== 1 || ! is_numeric($branch)
            ) {
                throw ApiKeyException::forbidden();
            }

            return $callback((int) $branch, (int) $operator->id);
        } catch (ApiKeyException $e) {
            return response()->json([
                'error' => array_filter([
                    'code' => $e->errorCode,
                    'message' => __($e->getMessage(), $e->replace),
                    'fields' => $e->fields === [] ? null : array_map(
                        fn(array $keys) => array_map(fn($k) => __($k, $e->replace), $keys),
                        $e->fields,
                    ),
                ], fn($v) => $v !== null),
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

    private function payload(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(['payload' => $payload, 'empty' => false, 'meta' => ['timestamp' => time()]], $status);
    }
}
