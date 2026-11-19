<?php

namespace App\Services\SystemLog\Domain\Error;

use InvalidArgumentException;

/**
 * فهرست دسته‌های خطا (داده از جدول system_log_categories، نه کد):
 *   source = «کجا رخ داد» (api، job، frontend، ...)
 *   type   = «چه نوعی» (database، validation، ...) با classes: exceptionهایی که به این نوع می‌روند
 *   all    = ردیف ویژه‌ی «همه‌ی دسته‌ها» برای دسترسی؛ هرگز به خطا نسبت داده نمی‌شود
 * Domain به دیتابیس و config دست نمی‌زند؛ ردیف‌ها را سازنده می‌گیرد (CategoryService).
 */
final class ErrorTaxonomy
{
    public const SOURCE = 'source';
    public const TYPE = 'type';
    public const ALL = 'all';

    /** نوع پیش‌فرض وقتی هیچ قاعده‌ای نخورد */
    public const UNKNOWN = 'unknown';

    /** @var array<int, array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array}> */
    private array $byId = [];

    /** @param  array<int, array{id: int, kind: string, key: string, title_fa?: ?string, title_en?: ?string, classes?: ?array}>  $rows */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->byId[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'key' => (string) $row['key'],
                'title_fa' => $row['title_fa'] ?? null,
                'title_en' => $row['title_en'] ?? null,
                'classes' => $row['classes'] ?? null,
            ];
        }

        $this->id(self::TYPE, self::UNKNOWN);
    }

    /** @return array<int, array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array}> */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /** @return array<int, array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array}> */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->byId, fn (array $row) => $row['kind'] === $kind));
    }

    public function has(int $id): bool
    {
        return isset($this->byId[$id]);
    }

    public function find(int $id): ?array
    {
        return $this->byId[$id] ?? null;
    }

    /** شناسه از روی (kind، key)؛ نبود ← استثنا */
    public function id(string $kind, string $key): int
    {
        return $this->idOrNull($kind, $key) ?? throw new InvalidArgumentException("Unknown error category [{$kind}:{$key}]");
    }

    public function idOrNull(string $kind, string $key): ?int
    {
        foreach ($this->byId as $row) {
            if ($row['kind'] === $kind && $row['key'] === $key) {
                return $row['id'];
            }
        }

        return null;
    }

    public function key(int $id): ?string
    {
        return $this->byId[$id]['key'] ?? null;
    }

    /** نام نمایشی به زبان locale (fa یا غیر آن)؛ بدون نام ← خود key */
    public function title(int $id, string $locale): string
    {
        $row = $this->byId[$id] ?? null;

        if ($row === null) {
            return '';
        }

        $title = $locale === 'fa' ? ($row['title_fa'] ?: $row['title_en']) : ($row['title_en'] ?: $row['title_fa']);

        return $title ?: $row['key'];
    }

    /**
     * شناسه‌ی «نوع» خطا: اول از روی کلاس exception؛ اگر نخورد و نوعی هم‌کلید با «منبع» وجود داشته باشد (مثل external) همان؛
     * وگرنه unknown.
     */
    public function classify(?string $exceptionClass, int $sourceId): int
    {
        if ($exceptionClass !== null && $exceptionClass !== '') {
            foreach ($this->byId as $row) {
                foreach ($row['kind'] === self::TYPE ? ($row['classes'] ?? []) : [] as $class) {
                    if (is_a($exceptionClass, $class, true)) {
                        return $row['id'];
                    }
                }
            }
        }

        $sameKey = isset($this->byId[$sourceId]) ? $this->idOrNull(self::TYPE, $this->byId[$sourceId]['key']) : null;

        return $sameKey ?? $this->id(self::TYPE, self::UNKNOWN);
    }
}
