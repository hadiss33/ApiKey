<?php

namespace App\Services\SystemLog\Application\Contracts;

use App\Services\SystemLog\Application\DTOs\LogRecord;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Audit\AuditContext;
use DateTimeInterface;

/**
 * پورت ذخیره‌سازی لاگ فعالیت و «نوع»های آن (پیاده‌سازی: EloquentAuditStore).
 * نوع رویداد داده است، نه کد: جدول system_log_types منبع حقیقت است.
 */
interface AuditStore
{
    /**
     * شناسه‌ی نوع رویداد از روی کد. نوع ناشناخته خودش ساخته می‌شود (دسته‌ی پیش‌فرض + یک هشدار)
     * تا داده‌ی ممیزی از دست نرود؛ دسته و نام نمایشی بعداً از پنل تعیین می‌شود.
     */
    public function resolveType(string $code): int;

    /** ذخیره‌ی یک ردیف و جزئیاتش در یک تراکنش؛ شناسه‌ی ردیف را برمی‌گرداند. */
    public function store(int $typeId, ?string $subjectId, AuditContext $context, ?array $details): int;

    /**
     * تاریخچه‌ی یک موجودیت در یک شعبه، جدیدترین اول. typeOrCategory هم کد نوع (SendOtp) می‌تواند باشد هم نام دسته.
     *
     * @return array<int, LogRecord>
     */
    public function history(string $typeOrCategory, string $subjectId, int $branchId, ?DateTimeInterface $from, ?DateTimeInterface $to, int $limit): array;

    /**
     * لیست لاگ‌های «یک شعبه»، جدیدترین اول. فیلترها: type (کد)، category، subject_id، operator_id، from، to.
     *
     * @param  array<string, mixed>  $filters
     * @return Page<LogRecord>
     */
    public function search(array $filters, int $branchId, int $page, int $perPage): Page;

    /**
     * دسته‌های رویداد موجود (داینامیک).
     *
     * @return array<int, string>
     */
    public function categories(): array;

    /**
     * همه‌ی نوع‌ها.
     *
     * @return array<int, array{id: int, code: string, category: string, title_fa: ?string, title_en: ?string}>
     */
    public function types(): array;

    /** تعیین دسته و نام نمایشی یک نوع (پنل). false اگر نوع نباشد. */
    public function updateType(int $id, ?string $category, ?string $titleFa, ?string $titleEn): bool;
}
