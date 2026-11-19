<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Http\Middleware\AuthGuest;
use App\Services\ApiKey\Application\Contracts\PathSecurity;
use App\Services\ApiKey\Application\DTOs\PreparedKey;
use App\Services\ApiKey\Application\Services\ApiKeyService;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * احراز هویت و مجوز درخواستِ دارای کلید API.
 * جای DomainAccess + IpTrust + AuthWithJWT + AuthGuest.
 *
 *   هویت       : PreparedKey (رکورد + قانون‌های جمع‌شده + اپراتور پاک)
 *   مجوز        : prefix route ← قانون‌های اجتماعی (بلندترین ← کوتاه‌ترین، write شامل read)
 *   مسیر ممنوع : «هرگز» در config
 *   امنیت مسیر : PathSecurity (جای IpTrust)
 *   attribute   : branch/office (از کلید، نه هدر Domain)،
 *                 group/level/operator (اپراتور پاک‌سازی‌شده)،
 *                 api_key (هویت کلید برای ثبت در رکوردها)
 */
final class ApiKeyAuth
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly CategoryRegistry $categories,
        private readonly PathSecurity $paths,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = ApiKeyHeader::extract($request);

        if ($plain === null) {
            return $this->error(ApiKeyException::unauthenticated());
        }

        $ip = $this->clientIp($request);
        $failKey = 'api_key:fail:' . sha1((string) $ip);

        if (RateLimiter::tooManyAttempts($failKey, max(1, (int) config('api_key.failures.max_attempts', 20)))) {
            return $this->tooMany('api_key.auth.too_many_failures', RateLimiter::availableIn($failKey));
        }

        try {
            /** @var PreparedKey $prepared */
            $prepared = $this->keys->authenticate($plain, $ip, $request->headers->get('Origin'));
            $record = $prepared->record;

            // مجوز route (منطق اجتماعی + هرگز)
            $route = $request->route();
            $this->categories->authorize(
                $prepared->aggregatedRules,
                (string) $route?->uri(),
                $request->path(),
                $route !== null ? RouteLevel::for($route, $request->getMethod()) : ApiKeyRules::levelForMethod($request->getMethod()),
                $record->isPublic(),
            );

            // بررسی امنیت مسیر (جای PathSecurity در IpTrust)
            if (! $this->paths->allowed($request->path())) {
                throw ApiKeyException::sourceDenied();
            }

            // کلید test: سوییچ به دیتابیس/Redis demo (مثل DomainAccess)
            if ($record->environment === ApiKeyRules::ENV_TEST) {
                $this->switchToDemo($request);
            }
        } catch (ApiKeyException $e) {
            if ($e->errorCode === 4010) {
                RateLimiter::hit($failKey, max(60, (int) config('api_key.failures.decay_seconds', 600)));
            }

            return $this->error($e);
        }

        $operator = $prepared->sanitizedOperator;
        $limit = $record->rateLimitPerMinute ?? (int) config('api_key.rate_limit.' . ApiKeyRules::typeKey($record->type), 60);
        $rateKey = 'api_key:rate:' . $record->id;

        if ($limit > 0 && RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return $this->tooMany('api_key.auth.rate_limited', RateLimiter::availableIn($rateKey));
        }

        RateLimiter::hit($rateKey, 60);

        $request->attributes->add([
            'branch' => $record->branchId,
            'office' => $record->branchId,
            'group' => 'base',
            'level' => $operator->level ?? 1,
            'operator' => $operator,
            'api_key' => $record,
        ]);

        $this->keys->touch($record, $ip);

        return $next($request);
    }

    private function clientIp(Request $request): ?string
    {
        $ip = function_exists('getIp') ? getIp() : $request->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
    }

    /** سوییچ اتصال DB/Redis به demo (مثل DomainAccess برای domain های demo) */
    private function switchToDemo(Request $request): void
    {
        try {
            if (config('database.default') !== 'mysql_demo') {
                config(['database.default' => 'mysql_demo']);
                \Illuminate\Support\Facades\DB::purge('mysql');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ApiKey: demo DB switch failed', ['error' => $e->getMessage()]);
        }
    }

    private function error(ApiKeyException $e): JsonResponse
    {
        $error = ['code' => $e->errorCode, 'message' => __($e->getMessage(), $e->replace)];

        if ($e->required !== []) {
            $error['required'] = $e->required;
        }

        return response()->json(['error' => $error, 'meta' => ['timestamp' => time()]], $e->status);
    }

    private function tooMany(string $translationKey, int $retryAfter): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 4290, 'message' => __($translationKey)],
            'meta' => ['timestamp' => time()],
        ], 429, ['Retry-After' => max(1, $retryAfter)]);
    }
}
