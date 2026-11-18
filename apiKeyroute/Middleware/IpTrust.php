<?php

namespace App\Http\Middleware;

use App\Helpers\SecurityHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuthenticatorTrust;

class IpTrust
{
    public function handle(Request $request, Closure $next, ...$methods): Response
    {
        $clientIp = getIp();
        $hostIp = getHostIp();
        $secFetch = getSecFetch();
        $host = getDomain();
        $requestData = $request->all();

        $ipWhiteList = config('ip_trust.whitelist');

        if ($request->getHost() == 'bridge.airplus.app') {
            if (
                !in_array($clientIp, $ipWhiteList['core']) &&
                !in_array($clientIp, $ipWhiteList['safarmarket']) &&
                !in_array($clientIp, $ipWhiteList['sepehr360'])
            ) {

                return $this->createErrorResponse(1002, [
                    "ip"      => $clientIp,
                    "host"    => $request->getHost(),
                    "host_ip" => !$this->isLocalIp($hostIp) ? $hostIp : 'private ip',
                    "message" => __('middleware.not_allow_to_system', ['value' => $clientIp . "/" . (!$this->isLocalIp($hostIp) ? $hostIp : "private ip")])
                ]);
            }
        }

        if (($secFetch['site'] == 'cross-site' || $secFetch['mode'] == 'cors') && $this->isLocalIp($hostIp)) {

            return $next($request);
        }

        $checkPath = SecurityHelper::checkPathSecurity($request->path());
        if (!$checkPath) {

            return $this->createErrorResponse(1005, [
                "ip"      => $clientIp,
                "host_ip" => !$this->isLocalIp($hostIp) ? $hostIp : 'private ip',
                "message" => __('middleware.blocked', ['value' => $clientIp . "/" . (!$this->isLocalIp($hostIp) ? $hostIp : "private ip")])
            ], 403);
        }
        $cacheKey = "ip_access_{$hostIp}";
        // نکته: این کلوژر فقط داده برمی‌گرداند (نه side-effect روی $request)، چون
        // Cache::remember فقط در cache-miss کلوژر را اجرا می‌کند — هر side-effect
        // اینجا در بقیه‌ی cache-hit ها (اکثر درخواست‌ها طی ۱۰ دقیقه‌ی TTL) هرگز
        // اجرا نمی‌شد.
        $accessData = Cache::remember($cacheKey, 600, function () use ($request, $hostIp, $clientIp) {
            $authTrust = AuthenticatorTrust::select('type')
                ->where(function ($q) use ($request) {
                    $q->when($request->has('branch'), function ($query) use ($request) {
                        $query->where(function ($q) use ($request) {
                            $q->where('target', $request->get('branch'))
                                ->orWhereNull('target');
                        });
                    });
                })->orWhere(function ($q) use ($hostIp, $clientIp) {
                    $q->where([
                        ['object_type', '=', 'ip'],
                        ['status', '=', 1]
                    ])
                        ->where(function ($query) use ($hostIp, $clientIp) {
                            $mysqlMaskFormula = "(4294967295 - (POW(2, 32 - SUBSTRING_INDEX(object, '/', -1)) - 1))";

                            $query->whereRaw("
                            (INET_ATON(?) & {$mysqlMaskFormula})
                            = (INET_ATON(SUBSTRING_INDEX(object, '/', 1)) & {$mysqlMaskFormula})
                        ", [$hostIp])
                                ->orWhereRaw("
                            (INET_ATON(?) & {$mysqlMaskFormula})
                            = (INET_ATON(SUBSTRING_INDEX(object, '/', 1)) & {$mysqlMaskFormula})
                        ", [$clientIp]);
                        });
                })
                ->first();

            if (!$authTrust) {
                return ['allowed' => false, 'type' => null];
            }

            return ['allowed' => true, 'type' => $authTrust['type']];
        });

        // تزریق branch همیشه خارج از کش انجام می‌شود تا در cache-hit هم اجرا شود
        if (($accessData['type'] ?? null) === 'api') {
            $rawId = $request->get('id', null);
            $branchId = $rawId && is_numeric($rawId) ? ($rawId - 1_000) : null;
            if (!$request->has('branch') && !$request->get('branch')) {
                $request->attributes->add(['branch' => $branchId]);
            }
        }

        if (!($accessData['allowed'] ?? false) && !$this->isLocalIp($hostIp)) {

            return $this->createErrorResponse(1002, [
                "ip"      => $clientIp,
                "host"    => $request->getHost(),
                "host_ip" => !$this->isLocalIp($hostIp) ? $hostIp : 'private ip',
                "message" => __('middleware.not_allow_to_system', ['value' => $clientIp . "/" . (!$this->isLocalIp($hostIp) ? $hostIp : "private ip")])
            ]);
        }


        return $next($request);
    }

    private function createErrorResponse(int $code, array $errorData, int $statusCode = 400): Response
    {
        return response()->json([
            "status"  => false,
            "time"    => time(),
            "error"   => array_merge(["code" => $code], $errorData),
            "support" => config('support.contact')
        ], $statusCode);
    }

    private function isLocalIp(string $ip): bool
    {
        if (empty($ip)) return false;
        if ($ip === '127.0.0.1' || $ip === '::1') return true;
        if (preg_match('/^10\./', $ip)) return true;
        if (preg_match('/^192\.168\./', $ip)) return true;
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) return true;
        if (strpos($ip, 'fc00:') === 0 || strpos($ip, 'fd00:') === 0) return true;
        return false;
    }
}
