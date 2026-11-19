<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\ErrorStore;
use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Access\AccessScope;
use App\Services\SystemLog\Domain\Error\ErrorEntry;
use App\Services\SystemLog\Domain\Error\ErrorIdentity;
use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;
use App\Services\SystemLog\Domain\Error\LogLevel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * ثبت و خواندن خطا. روند ثبت همین‌جا خوانده می‌شود (هر قدم یک متد):
 *
 *   report()/reportMessage()  ← محافظ حلقه + شکست‌ناپذیری
 *     entryFrom…()            ← جمع‌کردن اطلاعات: منبع، نوع، کد HTTP، stack، request (رازها پاک)
 *     ErrorIdentity           ← fingerprint
 *     ErrorStore::record()    ← ذخیره‌ی اتمیک (جدید = ردیف تازه، تکراری = شمارنده)
 *     ErrorNotifier           ← اعلان فقط برای خطای یکتای جدید/برگشته
 *   ← کد پیگیری (E-…)
 *
 * ثبت (و اعلانش) هرگز exception نمی‌اندازد؛ شکست فقط Log::error است و null برمی‌گردد.
 * خواندن/تغییر وضعیت با AccessScope است: خطای خارج از محدوده شبیه «نبود» است.
 *
 * گزینه‌های اختیاری $options: source (کلید دسته مثل frontend)، level (LogLevel)، code، status_code، location، external (آرایه)،
 * context (آرایه)، file و line (فقط پیام دستی)، actor_id، notify (پیش‌فرض true؛ فقط reportMessage)،
 * with_request (پیش‌فرض true؛ false = جزئیات request ذخیره نشود).
 */
final class ErrorService
{
    /** جلوگیری از حلقه: خطای هنگام ثبت خطا دوباره ثبت نمی‌شود */
    private static bool $reporting = false;

    public function __construct(
        private readonly ErrorStore $store,
        private readonly RequestContext $context,
        private readonly Redactor $redactor,
        private readonly CategoryService $categories,
        // اجباری (نه پیش‌فرض null): لاراول پارامتر دارای مقدار پیش‌فرض را تا bind نشده باشد تزریق نمی‌کند و اعلان‌ها بی‌صدا قطع می‌شدند
        private readonly ErrorNotifier $notifier,
    ) {}

    // ───────────────────────── ثبت ─────────────────────────

    /** @return string|null کد پیگیری (E-…)، یا null اگر ثبت ممکن نشد */
    public function report(Throwable $e, array $options = []): ?string
    {
        return $this->safely(fn (ErrorTaxonomy $tax) => $this->entryFromThrowable($tax, $e, $options), $e->getMessage());
    }

    /**
     * گزینه‌ی notify=false: ثبت بدون اعلان (مثلاً خطاهای ارسالی سیستم‌های بیرونی با کلید API).
     *
     * @return string|null کد پیگیری، یا null اگر ثبت ممکن نشد
     */
    public function reportMessage(string $message, array $options = []): ?string
    {
        return $this->safely(fn (ErrorTaxonomy $tax) => $this->entryFromMessage($tax, $message, $options), $message, (bool) ($options['notify'] ?? true));
    }

    /** @param  callable(ErrorTaxonomy): ErrorEntry  $makeEntry */
    private function safely(callable $makeEntry, string $originalMessage, bool $notify = true): ?string
    {
        if (self::$reporting) {
            return null;
        }

        self::$reporting = true;

        try {
            $entry = $makeEntry($this->categories->taxonomy());
            // ادغام: dedupe_hours>0 ← «کاربر + نوع + متد» در بازه‌های N ساعته یک ردیف؛ تکرار فقط شمارنده را بالا می‌برد
            $window = max(0, (int) config('system_log.error.dedupe_hours', 0));
            $fingerprint = ErrorIdentity::fingerprint($entry, $window, (array) config('system_log.error.dedupe_keep_message', []));
            $record = $this->store->record($entry, $fingerprint, refreshSnapshot: $window === 0);

            if ($notify) {
                $this->notify($record);
            }

            return $record->ref;
        } catch (Throwable $failure) {
            Log::error('SystemLog: storing the error report failed', ['original_message' => mb_substr($originalMessage, 0, 500), 'failure' => $failure->getMessage()]);

            return null;
        } finally {
            self::$reporting = false;
        }
    }

    private function notify(ErrorRecord $record): void
    {
        try {
            $this->notifier->consider($record);
        } catch (Throwable $failure) {
            Log::warning('SystemLog: deciding the error notification failed', ['error_id' => $record->id, 'failure' => $failure->getMessage()]);
        }
    }

    // ───────────────────────── خواندن ─────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     * @return Page<ErrorRecord>
     */
    public function search(array $filters, AccessScope $scope, int $page = 1, int $perPage = 20): Page
    {
        return $this->store->search($filters, $scope, $page, $perPage);
    }

    /** جزئیات با کد پیگیری؛ null اگر نیست یا در محدوده نیست (یکسان) */
    public function find(string $ref, AccessScope $scope): ?ErrorRecord
    {
        $id = ErrorIdentity::idFromRef($ref);

        return $id === null ? null : $this->store->find($id, $scope);
    }

    /**
     * تغییر وضعیت/یادداشت؛ هر کسی که خطا را می‌بیند می‌تواند. false = پیدا نشد (یا در محدوده نیست).
     * $operatorId برای درخواست با کلید API null است (resolved_by خالی می‌ماند).
     */
    public function updateStatus(AccessScope $scope, string $ref, ErrorStatus $status, ?string $note, ?int $operatorId): bool
    {
        $id = ErrorIdentity::idFromRef($ref);

        return $id !== null
            && $this->store->find($id, $scope) !== null
            && $this->store->updateStatus($id, $status, $note, $operatorId);
    }

    /**
     * پاکسازی خطاهای حل‌شده (کرون system-log:prune-errors). $olderThanDays خالی = مقدار config
     * (error.prune.resolved_older_than_days؛ ۰ = همه‌ی حل‌شده‌ها). با $dryRun فقط می‌شمارد.
     */
    public function pruneResolved(?int $olderThanDays = null, bool $dryRun = false): int
    {
        $days = max(0, $olderThanDays ?? (int) config('system_log.error.prune.resolved_older_than_days', 0));

        return $this->store->pruneResolved(now()->subDays($days), $dryRun);
    }

    // ───────────────────────── جمع‌کردن اطلاعات ─────────────────────────

    private function entryFromThrowable(ErrorTaxonomy $tax, Throwable $e, array $options): ErrorEntry
    {
        $source = $this->sourceId($tax, $options);

        return $this->entry(
            tax: $tax,
            message: $e->getMessage() !== '' ? $e->getMessage() : $e::class,
            class: $e::class,
            file: $this->relativePath($e->getFile()),
            line: $e->getLine(),
            stack: $this->stack($e),
            chain: $this->chain($e),
            source: $source,
            statusCode: $options['status_code'] ?? $this->statusCodeFor($tax, $e, $source),
            options: $options,
        );
    }

    private function entryFromMessage(ErrorTaxonomy $tax, string $message, array $options): ErrorEntry
    {
        return $this->entry(
            tax: $tax,
            message: $message,
            class: null,
            file: isset($options['file']) ? $this->relativePath((string) $options['file']) : null,
            line: isset($options['line']) ? (int) $options['line'] : null,
            stack: null,
            chain: [],
            source: $this->sourceId($tax, $options),
            statusCode: $options['status_code'] ?? null,
            options: $options,
        );
    }

    private function entry(ErrorTaxonomy $tax, string $message, ?string $class, ?string $file, ?int $line, ?string $stack, array $chain, int $source, ?int $statusCode, array $options): ErrorEntry
    {
        $cfg = (array) config('system_log.error', []);

        $details = array_filter([
            // with_request=false: خطای «گزارش‌شده» توسط کلاینت (body درخواست خودِ گزارش است، نه علت خطا)
            'request' => ($options['with_request'] ?? true) && $this->context->isHttp() ? $this->context->requestDetails((array) ($cfg['request_headers'] ?? [])) : null,
            'runtime' => $this->runtime(),
            'external' => $options['external'] ?? null,
            'exception_chain' => $chain,
            'context' => $options['context'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $details = $this->redactor->fit(
            $this->redactor->redact($details),
            (int) ($cfg['max_details_bytes'] ?? 16384),
            (int) ($cfg['max_string_length'] ?? 1000),
        );

        return new ErrorEntry(
            source: $source,
            type: $tax->classify($class, $source),
            level: $options['level'] ?? ($statusCode !== null && $statusCode >= 400 && $statusCode < 500 ? LogLevel::Warning : LogLevel::Error),
            message: $this->redactor->scrubString(mb_substr($message, 0, (int) ($cfg['max_message_length'] ?? 1000))),
            exceptionClass: $class !== null ? mb_substr($class, 0, 255) : null,
            code: isset($options['code']) ? mb_substr((string) $options['code'], 0, 60) : null,
            file: $file !== null ? mb_substr($file, 0, 300) : null,
            line: $line,
            branchId: $this->context->branchId(),
            statusCode: $statusCode,
            location: ($loc = $options['location'] ?? $this->context->location()) === null ? null : mb_substr((string) $loc, 0, 300),
            actorType: $this->context->actorType(),
            actorId: isset($options['actor_id']) ? (int) $options['actor_id'] : $this->context->actorId(),
            ip: $this->context->ip(),
            stack: $stack,
            details: $details === [] ? null : $details,
            occurredAt: now(),
        );
    }

    /** «کجا رخ داد»: گزینه‌ی صریح (کلید)، وگرنه از محیط: api/web در HTTP؛ job/cron/console در artisan */
    private function sourceId(ErrorTaxonomy $tax, array $options): int
    {
        $given = $options['source'] ?? null;

        if (is_string($given) && ($id = $tax->idOrNull(ErrorTaxonomy::SOURCE, $given)) !== null) {
            return $id;
        }

        if ($this->context->isHttp()) {
            return $tax->id(ErrorTaxonomy::SOURCE, $this->context->isApiRequest() ? 'api' : 'web');
        }

        $command = (string) $this->context->consoleCommand();

        return $tax->id(ErrorTaxonomy::SOURCE, match (true) {
            str_starts_with($command, 'queue:') => 'job',
            str_starts_with($command, 'schedule:') => 'cron',
            default => 'console',
        });
    }

    /** کد HTTP فقط برای منبع api/web از نوع exception مشتق می‌شود */
    private function statusCodeFor(ErrorTaxonomy $tax, Throwable $e, int $source): ?int
    {
        if (! in_array($tax->key($source), ['api', 'web'], true)) {
            return null;
        }

        return match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof ValidationException => 422,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            // خطای کسب‌وکار/کلاینتی با کد شبه‌HTTP (مثل ApiException(…, 422) یا ClientException) ← همان کد و شدت warning، نه ۵۰۰
            is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 500 => $e->getCode(),
            default => 500,
        };
    }

    /** بیرون از HTTP: دستور artisan و آرگومان‌هایش */
    private function runtime(): ?array
    {
        if ($this->context->isHttp() || ($command = $this->context->consoleCommand()) === null) {
            return null;
        }

        return ['command' => trim($command . ' ' . implode(' ', $this->context->consoleArguments()))];
    }

    /** exceptionهای قبلی (علت اصلی) تا سقف config */
    private function chain(Throwable $e): array
    {
        $max = (int) config('system_log.error.previous_exceptions', 3);
        $chain = [];

        for ($p = $e->getPrevious(); $p && count($chain) < $max; $p = $p->getPrevious()) {
            $chain[] = ['class' => $p::class, 'message' => mb_substr($p->getMessage(), 0, 300), 'file' => $this->relativePath($p->getFile()), 'line' => $p->getLine()];
        }

        return $chain;
    }

    /** N فریم اول stack؛ با stack_skip_vendor فریم‌های خود برنامه اولویت دارند */
    private function stack(Throwable $e): ?string
    {
        $frames = $e->getTrace();

        if ($frames === []) {
            return null;
        }

        $limit = max(1, (int) config('system_log.error.stack_lines', 4));
        $picked = [];

        if (config('system_log.error.stack_skip_vendor', true)) {
            foreach ($frames as $frame) {
                if (isset($frame['file']) && ! str_contains(str_replace('\\', '/', $frame['file']), '/vendor/')) {
                    $picked[] = $frame;
                    if (count($picked) >= $limit) {
                        break;
                    }
                }
            }
        }

        $lines = [];
        foreach ($picked ?: array_slice($frames, 0, $limit) as $i => $f) {
            $where = isset($f['file']) ? $this->relativePath($f['file']) . ':' . ($f['line'] ?? '?') : '[internal]';
            $lines[] = '#' . $i . ' ' . $where . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
        }

        return $this->redactor->scrubString(implode("\n", $lines));
    }

    /** مسیر نسبی به ریشه‌ی پروژه با «/» (نه مسیر کامل سرور) */
    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', base_path()), '/') . '/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
