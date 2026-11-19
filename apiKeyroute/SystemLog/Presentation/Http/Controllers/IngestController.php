<?php

namespace App\Services\SystemLog\Presentation\Http\Controllers;

use App\Jobs\SystemLog;
use App\Services\ApiKey\Application\Services\ApiKeyContext;
use App\Services\SystemLog\Application\Services\ErrorService;
use App\Services\SystemLog\Domain\Access\AccessException;
use App\Services\SystemLog\Domain\Error\LogLevel;
use App\Services\SystemLog\Presentation\Http\Requests\IngestRequest;
use Illuminate\Http\JsonResponse;

/**
 * دریافت خطا و لاگ فعالیت از سیستم‌های بیرونی (api/v2/system-log/ingest/...)، فقط با کلید API.
 * خواندن و تغییر وضعیت خطاها و لاگ‌ها route جدا ندارد: همان route‌های پنل (errors، audit-logs، ...) با کلید هم کار می‌کنند.
 *
 * route در فایل پنل (authWithJwt) است ولی با کلید هم باز می‌شود (دسته‌ی system_log_ingest در config/api_key.php:
 * فقط write، public). درخواست اپراتور (JWT) اینجا ۴۰۳ می‌گیرد: این دو endpoint فقط برای کلید است.
 * هویت ثبت‌شده در رکوردها = خود کلید (actor_type=api_key) از LaravelRequestContext.
 */
class IngestController extends SystemLogController
{
    public function __construct(private readonly ErrorService $errors) {}

    /** ارسال یک یا چند خطا؛ منبع از نوع کلید: عمومی ← frontend، محرمانه ← external */
    public function reportErrors(IngestRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $key = $this->requireKey();
            $source = $key->isPublic() ? 'frontend' : 'external';
            $notify = (bool) config('system_log.external.notify', false);
            $refs = [];

            foreach ($request->validated('errors') as $error) {
                $refs[] = $this->errors->reportMessage((string) $error['message'], array_filter([
                    'source' => $source,
                    'level' => isset($error['level']) ? $this->level($error['level']) : LogLevel::Error,
                    'code' => $error['code'] ?? null,
                    'file' => $error['file'] ?? null,
                    'line' => $error['line'] ?? null,
                    'location' => $error['url'] ?? null,
                    'external' => array_filter([
                        'stack' => $error['stack'] ?? null,
                        'user_agent' => $request->userAgent(),
                    ]),
                    'context' => $error['context'] ?? null,
                ], fn ($v) => $v !== null && $v !== []) + ['notify' => $notify, 'with_request' => false]);
            }

            return $this->payload(['refs' => $refs, 'recorded' => count(array_filter($refs))], 201);
        });
    }

    /**
     * ارسال یک لاگ فعالیت (صف snailJob، مثل بقیه‌ی لاگ‌ها). نوع با پیشوند ext_ ذخیره می‌شود تا
     * نوع‌های داخلی (مثل LoginColleague) جعل نشوند. فقط با driver=internal.
     */
    public function storeAudit(IngestRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireKey();

            if (config('system_log.driver', 'visa') !== 'internal') {
                return $this->error(5030, __('system_log.api.audit_not_recorded'), 503);
            }

            $data = $request->validated();
            SystemLog::dispatch([
                'type' => 'ext_' . $data['type'],
                'data' => $data['data'] ?? null,
                'goal' => $data['goal'] ?? null,
            ]);

            return $this->payload(['queued' => true], 202);
        });
    }

    /** درخواستِ با کلید (ApiKeyAuth رکورد را در attribute «api_key» گذاشته)؛ وگرنه ۴۰۳ */
    private function requireKey(): \App\Services\ApiKey\Application\DTOs\ApiKeyRecord
    {
        return ApiKeyContext::current() ?? throw AccessException::forbidden('system_log.api.key_only');
    }

    private function level(string $key): LogLevel
    {
        return collect(LogLevel::cases())->first(fn (LogLevel $l) => $l->key() === $key) ?? LogLevel::Error;
    }
}
