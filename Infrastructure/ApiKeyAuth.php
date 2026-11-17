<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\OperatorDirectory;
use App\Services\ApiKey\Application\Contracts\PathSecurity;
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
 * احراز هویت و مجوز درخواستِ دارای کلید API؛ جای DomainAccess + IpTrust + AuthWithJWT
 * (ReplacePanelAuth آن سه را برای همین route کنار می‌گذارد و این میدلور را می‌گذارد).
 *
 *   هویت  : کلید معتبر، باطل/منقضی نباشد، IP/Origin مجاز (جای اعتماد IP در IpTrust)
 *   مجوز  : prefix route ← دسته در config ← سطح از متد (GET=read، بقیه=write) ← دسترسی کلید
 *   امنیت مسیر: همان بررسی IpTrust (PathSecurity)
 *   attribute: branch/office (جای DomainAccess؛ هدر Domain نادیده است)،
 *              group/level/operator (جای AuthWithJWT؛ اپراتور سازنده‌ی کلید، فعال بودنش شرط است)،
 *              api_key (هویت کلید برای ثبت در رکوردها: ApiKeyContext)
 *
 * هر خطا ← پاسخ error با همان قالب پروژه؛ هرگز route بدون احراز باز نمی‌ماند.
 */
final class ApiKeyAuth
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly CategoryRegistry $categories,
        private readonly OperatorDirectory $operators,
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
            $record = $this->keys->authenticate($plain, $ip, $request->headers->get('Origin'));
            $this->categories->authorize($record, (string) $request->route()?->uri(), $request->getMethod());

            if (! $this->paths->allowed($request->path())) {
                throw ApiKeyException::sourceDenied();
            }

            $operator = $this->operators->activeOperator($record->createdBy) ?? throw ApiKeyException::creatorInactive();
        } catch (ApiKeyException $e) {
            // فقط «کلید نامعتبر» شمرده می‌شود (حدس‌زدن کلید)، نه دسترسی/اپراتور
            if ($e->errorCode === 4010) {
                RateLimiter::hit($failKey, max(60, (int) config('api_key.failures.decay_seconds', 600)));
            }

            return $this->error($e);
        }

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
