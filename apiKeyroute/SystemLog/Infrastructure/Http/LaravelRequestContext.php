<?php

namespace App\Services\SystemLog\Infrastructure\Http;

use App\Services\SystemLog\Application\Contracts\RequestContext;
use Illuminate\Http\Request;

/**
 * اجرای فعلی از روی request لاراول یا artisan. داده از attributeهای ست‌شده توسط میدلورها می‌آید
 * (branch ← DomainAccess؛ group و operator ← AuthWithJWT)، نه از query/body کلاینت.
 */
final class LaravelRequestContext implements RequestContext
{
    /** نام جاب در حال اجرا در ورکر صف (SystemLogServiceProvider از رویدادهای صف مقدار می‌دهد) */
    public static ?string $currentJob = null;

    private function request(): ?Request
    {
        return app()->runningInConsole() || ! app()->bound('request') ? null : request();
    }

    public function isHttp(): bool
    {
        return $this->request() !== null;
    }

    public function isApiRequest(): bool
    {
        return (bool) $this->request()?->is('api/*');
    }

    public function branchId(): ?int
    {
        $branch = $this->request()?->attributes->get('branch');

        return is_numeric($branch) ? (int) $branch : null;
    }

    public function actorType(): ?int
    {
        if ($this->apiKeyId() !== null) {
            return self::ACTOR_TYPES['api_key'];
        }

        $group = $this->request()?->attributes->get('group');

        return is_string($group) ? (self::ACTOR_TYPES[$group] ?? null) : null;
    }

    public function actorId(): ?int
    {
        // درخواست با کلید API: هویت = خود کلید (نه اپراتور)
        if (($keyId = $this->apiKeyId()) !== null) {
            return $keyId;
        }

        $operator = $this->request()?->attributes->get('operator');

        $id = match (true) {
            is_object($operator) => $operator->id ?? null,
            is_array($operator) => $operator['id'] ?? null,
            default => null,
        };

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * id کلید API درخواست (attribute «api_key» که میدلور apiKey می‌گذارد). عمداً بدون وابستگی به کلاس‌های
     * ماژول ApiKey (فقط ->id) تا SystemLog جداشدنی بماند.
     */
    private function apiKeyId(): ?int
    {
        $key = $this->request()?->attributes->get('api_key');
        $id = is_object($key) ? ($key->id ?? null) : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function ip(): ?string
    {
        $request = $this->request();

        if (! $request) {
            return null;
        }

        $ip = function_exists('getIp') ? getIp() : $request->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->request()?->userAgent();

        return $agent !== null && $agent !== '' ? mb_substr($agent, 0, 255) : null;
    }

    public function location(): ?string
    {
        $request = $this->request();

        if ($request) {
            return $request->method() . ' /' . ltrim($request->path(), '/');
        }

        // داخل ورکر صف: نام جاب در حال اجرا (SystemLogServiceProvider آن را از رویدادهای صف می‌گذارد)
        if (self::$currentJob !== null) {
            return 'job:' . self::$currentJob;
        }

        $command = $this->consoleCommand();

        return $command ? 'cmd:' . $command : null;
    }

    public function requestDetails(array $headerNames): ?array
    {
        $request = $this->request();

        if (! $request) {
            return null;
        }

        $headers = [];
        foreach ($headerNames as $name) {
            if (($value = $request->headers->get($name)) !== null) {
                $headers[$name] = $value;
            }
        }

        return array_filter([
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->isJson() ? $request->json()->all() : $request->post(),
            'headers' => $headers,
            'duration_ms' => defined('LARAVEL_START') ? (int) round((microtime(true) - LARAVEL_START) * 1000) : null,
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function consoleCommand(): ?string
    {
        if ($this->request() !== null) {
            return null;
        }

        $command = $_SERVER['argv'][1] ?? null;

        return $command ? (string) $command : null;
    }

    public function consoleArguments(int $max = 3): array
    {
        return $this->request() !== null ? [] : array_map('strval', array_slice((array) ($_SERVER['argv'] ?? []), 2, $max));
    }
}
