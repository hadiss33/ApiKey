<?php

namespace App\Services\ApiKey\Application\DTOs;

use App\Services\ApiKey\Domain\ApiKeyRules;
use DateTimeInterface;

/**
 * یک کلید API (و هویت آن برای سرویس‌های دیگر). id هویت ثابت کلید است و با چرخش عوض نمی‌شود.
 * keyHash/previousKeyHash فقط برای پاک‌کردن کش داخل ماژول‌اند و هرگز در خروجی نمی‌آیند.
 * plainKey فقط در پاسخ ساخت/چرخش پر است (کلید خام هرگز ذخیره نمی‌شود).
 */
final class ApiKeyRecord
{
    /**
     * @param  array<int, array<int, int>>  $grants  شناسه‌ی دسته (از config) ← سطح‌ها (1=read، 2=write)
     * @param  array<int, string>  $allowedIps
     * @param  array<int, string>  $origins
     */
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $type,
        public readonly int $environment,
        public readonly string $prefix,
        public readonly string $keyHash,
        public readonly ?string $previousKeyHash,
        public readonly ?DateTimeInterface $previousKeyExpiresAt,
        public readonly array $grants,
        public readonly array $allowedIps,
        public readonly array $origins,
        public readonly ?int $rateLimitPerMinute,
        public readonly ?DateTimeInterface $expiresAt,
        public readonly ?DateTimeInterface $lastUsedAt,
        public readonly ?string $lastUsedIp,
        public readonly int $createdBy,
        public readonly ?int $updatedBy,
        public readonly ?DateTimeInterface $rotatedAt,
        public readonly ?DateTimeInterface $revokedAt,
        public readonly ?int $revokedBy,
        public readonly ?DateTimeInterface $createdAt,
        public readonly ?string $plainKey = null,
    ) {}

    public function status(?DateTimeInterface $now = null): string
    {
        return ApiKeyRules::status($this->revokedAt, $this->expiresAt, $now ?? now());
    }

    public function isPublic(): bool
    {
        return $this->type === ApiKeyRules::TYPE_PUBLIC;
    }

    /** همین رکورد به‌همراه کلید خام (فقط برای پاسخ ساخت/چرخش) */
    public function withPlainKey(string $plainKey): self
    {
        $values = get_object_vars($this);
        $values['plainKey'] = $plainKey;

        return new self(...$values);
    }

    /** هویت برای ثبت در رکورد سرویس‌ها */
    public function actor(): array
    {
        return ['type' => 'api_key', 'id' => $this->id];
    }
}
