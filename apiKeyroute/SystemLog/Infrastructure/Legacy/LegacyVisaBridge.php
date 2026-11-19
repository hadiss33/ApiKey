<?php

namespace App\Services\SystemLog\Infrastructure\Legacy;

use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\DTOs\LogRecord;
use App\Services\SystemLog\Application\Services\AuditService;
use App\Services\SystemLog\Application\Services\ErrorService;
use Carbon\Carbon;

/**
 * پل سازگاری با امضای قدیمی Visa (تا فراخوانی‌های موجود بدون تغییر کار کنند):
 *   - report()   ← Visa::addSystemReport($data, $operator, $link)  → ثبت خطای جدید
 *   - showLogs() ← Visa::showSystemLogs(...)                        → تاریخچه‌ی لاگ فعالیت
 * (ثبت لاگ فعالیت از مسیر جاب App\Jobs\SystemLog مستقیم به AuditLogService می‌رود.)
 *
 * بخش اول: ثبت خطا
 * امضای قدیمی Visa::addSystemReport($data, $operator, $link) را به ثبت خطای جدید وصل می‌کند.
 *
 * ورودی قدیمی «آرایه‌ی آزاد» است؛ این کلاس شکل‌های رایج را تشخیص می‌دهد:
 *   - رشته                                         ← خود رشته = پیام
 *   - آرایه‌ی trace (خروجی getTrace())              ← پیام عمومی؛ فایل/خط از اولین فریم
 *   - ['برچسب' => [...]] (مثل error:trade-after-pay) ← «برچسب: اولین رشته‌ی داخلش»
 *   - لیست (مثل ["Not found reference 5 ..."])       ← اولین رشته
 *   - خطای ExternalServiceClient (service/url/exception) ← منبع external با جزئیات
 *   - $link پر باشد (CoreController: گزارش ارسالی از فرانت) ← منبع frontend، location = لینک صفحه
 * هر رشته‌ی «path.php:123» داخل داده، به‌عنوان فایل و خط خطا شناخته می‌شود (برای fingerprint دقیق‌تر).
 * کل داده‌ی اصلی هم در details.context می‌ماند (پس از حذف رازها و اعمال سقف حجم).
 *
 * خروجی همان شکل قدیمی است ('Status' / 'Code' / 'Message')، با این تفاوت‌ها:
 *   - Code کد پیگیری جدید است (مثل E-OA8)، نه id+10000
 *   - در شکست ثبت، دیگر Data و Trace (که اطلاعات داخلی نشت می‌داد) برنمی‌گردد
 */
class LegacyVisaBridge
{
    public function __construct(
        private readonly ErrorService $errors,
        private readonly AuditService $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * جایگزین Visa::showSystemLogs: تاریخچه‌ی لاگ فعالیت، با همان شکل خروجی قدیمی.
     *
     * @param  array  $type      فهرست ['type' => کد نوع یا نام دسته، 'id' => شناسه‌ی موجودیت] (ورودی ممکن است از کلاینت بیاید)
     * @param  mixed  $datetime  false یا [از، تا]؛ قبلاً نادیده گرفته می‌شد، حالا اعمال می‌شود
     * @param  int    $limit     حداکثر تعداد رکورد به‌ازای هر عضو $type؛ قبلاً نادیده گرفته می‌شد، حالا اعمال می‌شود
     */
    public function showLogs(array $type, mixed $datetime = false, int $limit = 15): array
    {
        // جداسازی شعبه: بدون شعبه‌ی مشخص (middleware) هیچ لاگی برنمی‌گردد
        $branch = $this->context->branchId();

        if ($branch === null) {
            return [];
        }

        [$from, $to] = $this->window($datetime);
        $items = [];

        foreach ($type as $value) {
            if (! is_array($value) || ! isset($value['type'], $value['id']) || ! is_scalar($value['type']) || ! is_scalar($value['id'])) {
                continue;
            }

            foreach ($this->audit->history((string) $value['type'], (string) $value['id'], $branch, $from, $to, $limit) as $record) {
                $items[] = $this->legacyItem($record);
            }
        }

        return $items;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function window(mixed $datetime): array
    {
        if (! is_array($datetime) || count($datetime) < 2) {
            return [null, null];
        }

        try {
            return [Carbon::parse(array_values($datetime)[0]), Carbon::parse(array_values($datetime)[1])];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /**
     * شکل قدیمی Visa::showSystemLogs: ip، browser، type، description (مقدارهای جزئیات به‌صورت فهرست)، created_at،
     * operator، goal و نام اپراتور. به‌علاوه‌ی type_code (کد نوع) و actor (کاربر غیر-اپراتور).
     * «type» نام نمایشی نوع از lang است (برای نمایش در UI)؛ اگر فرانت به کد وابسته است از type_code بخواند.
     */
    private function legacyItem(LogRecord $record): array
    {
        $details = $record->details;
        $actor = $details['_actor'] ?? null;
        unset($details['_actor']);

        // نام نمایشی از جدول نوع‌ها (قابل تعیین از پنل)؛ بدون نام ← خود کد
        $title = app()->getLocale() === 'fa' ? ($record->titleFa ?: $record->titleEn) : ($record->titleEn ?: $record->titleFa);

        return [
            'ip' => $record->ip,
            'browser' => $record->userAgent,
            'type' => $title ?: $record->type,
            'type_code' => $record->type,
            'description' => $details ? $this->flatten($details) : '',
            'created_at' => $record->createdAt->format('Y-m-d H:i:s'),
            'operator' => $record->operatorId,
            'goal' => $record->subjectId,
            'first_name' => $record->operatorFirstName,
            'last_name' => $record->operatorLastName,
            'personnel_id' => $record->operatorPersonnelId,
            'actor' => $actor,
        ];
    }

    /** مقدارهای آرایه‌ی تودرتو به‌صورت یک فهرست تخت (همان رفتار Visa::flattenArray) */
    private function flatten(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    public function report(mixed $data = null, mixed $operator = false, ?string $link = null): array
    {
        [$message, $options] = $this->translate($data, $operator, $link);

        $ref = $this->errors->reportMessage($message, $options);

        if ($ref === null) {
            return ['Status' => false, 'Code' => '500', 'Message' => __('system_log.report_failed')];
        }

        return [
            'Status' => false,
            'Code' => $ref,
            'Message' => __('system_log.report_created', ['code' => $ref]),
        ];
    }

    /**
     * @return array{0: string, 1: array}  [پیام، گزینه‌های ErrorService]
     */
    private function translate(mixed $data, mixed $operator, ?string $link): array
    {
        $options = [];

        $actorId = $this->operatorId($operator);
        if ($actorId !== null) {
            $options['actor_id'] = $actorId;
        }

        // گزارش ارسالی از فرانت
        if ($link !== null && $link !== '') {
            $options['source'] = 'frontend';
            $options['location'] = $link;
        }

        if (! is_array($data)) {
            $text = is_scalar($data) ? trim((string) $data) : '';

            return [$text !== '' ? $text : __('system_log.unspecified'), $options];
        }

        // خطای شکست درخواست به سرویس بیرونی (ExternalServiceClient)
        if (isset($data['service'], $data['url'], $data['exception'])) {
            $options['source'] = 'external';
            $options['location'] = 'external:' . $this->stringify($data['service']);
            $options['external'] = [
                'service' => $this->stringify($data['service']),
                'url' => $this->stringify($data['url']),
                'method' => isset($data['method']) ? $this->stringify($data['method']) : null,
            ];
            $options['context'] = array_diff_key($data, array_flip(['service', 'url', 'method', 'exception']));

            return [$this->stringify($data['exception']), $options];
        }

        // آرایه‌ی trace (خروجی getTrace()): پیام ندارد، فقط محل
        if ($this->looksLikeTrace($data)) {
            foreach ($data as $frame) {
                if (is_array($frame) && isset($frame['file'])) {
                    $options['file'] = (string) $frame['file'];
                    $options['line'] = isset($frame['line']) ? (int) $frame['line'] : null;
                    break;
                }
            }
            $options['context'] = ['trace' => $this->slimTrace($data)];

            return [__('system_log.unspecified'), $options];
        }

        [$file, $line] = $this->findFileLine($data);
        if ($file !== null) {
            $options['file'] = $file;
            $options['line'] = $line;
        }

        $options['context'] = $data;

        return [$this->describe($data), $options];
    }

    /** پیام از داده‌ی آزاد: «برچسب: اولین رشته» یا فقط یکی از آن‌ها */
    private function describe(array $data): string
    {
        $label = null;
        $inner = $data;

        foreach ($data as $key => $value) {
            if (is_string($key) && $key !== '') {
                $label = $key;
                $inner = is_array($value) ? $value : [$value];
                break;
            }
        }

        $text = $this->firstString($inner);

        return match (true) {
            $label !== null && $text !== null => $label . ': ' . $text,
            $label !== null => $label,
            $text !== null => $text,
            default => __('system_log.unspecified'),
        };
    }

    /** اولین رشته‌ی غیرخالی (که «فایل:خط» نباشد)، تا عمق ۲ */
    private function firstString(array $items, int $depth = 0): ?string
    {
        foreach ($items as $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '' && ! $this->isFileLine($value)) {
                    return mb_substr($value, 0, 300);
                }
            } elseif (is_array($value) && $depth < 2) {
                $found = $this->firstString($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?int} */
    private function findFileLine(array $items, int $depth = 0): array
    {
        foreach ($items as $value) {
            if (is_string($value) && preg_match('/^(.+\.php):(\d+)$/i', trim($value), $m)) {
                return [$m[1], (int) $m[2]];
            }
            if (is_array($value) && $depth < 2) {
                $found = $this->findFileLine($value, $depth + 1);
                if ($found[0] !== null) {
                    return $found;
                }
            }
        }

        return [null, null];
    }

    private function isFileLine(string $value): bool
    {
        return (bool) preg_match('/^.+\.php:\d+$/i', $value);
    }

    private function looksLikeTrace(array $data): bool
    {
        return isset($data[0]) && is_array($data[0]) && array_key_exists('function', $data[0]);
    }

    /** فقط ۱۰ فریم اول و بدون args (که ممکن است آبجکت/راز داشته باشد) */
    private function slimTrace(array $trace): array
    {
        $slim = [];

        foreach (array_slice($trace, 0, 10) as $frame) {
            if (is_array($frame)) {
                $slim[] = array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function']));
            }
        }

        return $slim;
    }

    private function operatorId(mixed $operator): ?int
    {
        $id = match (true) {
            is_array($operator) => $operator['id'] ?? null,
            is_object($operator) => $operator->id ?? null,
            default => $operator,
        };

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
