<?php

namespace App\Services\ApiKey\Presentation;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * خروجی یک کلید برای پنل. hash هرگز بیرون نمی‌آید؛ کلید خام (key) فقط در پاسخ ساخت/چرخش.
 * categories = شناسه‌ی دسته‌های وصل‌شده؛ effective_access = اجتماع قانون‌های آن‌ها (دقیقاً چه چیزی باز است).
 *
 * @property ApiKeyRecord $resource
 */
class ApiKeyResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ApiKeyRecord $k */
        $k = $this->resource;

        $data = [
            'id' => $k->id,
            'name' => $k->name,
            'description' => $k->description,
            'type' => ApiKeyRules::typeKey($k->type),
            'environment' => ApiKeyRules::environmentKey($k->environment),
            'prefix' => $k->prefix,
            'status' => $k->status(),
            'categories' => $k->categoryIds,
            'effective_access' => app(CategoryRegistry::class)->effectiveAccess($k->categoryIds),
            'allowed_ips' => $k->allowedIps,
            'origins' => $k->origins,
            'rate_limit_per_minute' => $k->rateLimitPerMinute,
            'expires_at' => $k->expiresAt?->format(DATE_ATOM),
            'last_used_at' => $k->lastUsedAt?->format(DATE_ATOM),
            'last_used_ip' => $k->lastUsedIp,
            'previous_key_expires_at' => $k->previousKeyExpiresAt?->format(DATE_ATOM),
            'rotated_at' => $k->rotatedAt?->format(DATE_ATOM),
            'revoked_at' => $k->revokedAt?->format(DATE_ATOM),
            'created_by' => $k->createdBy,
            'updated_by' => $k->updatedBy,
            'revoked_by' => $k->revokedBy,
            'created_at' => $k->createdAt?->format(DATE_ATOM),
        ];

        if ($k->plainKey !== null) {
            $data['key'] = $k->plainKey;
        }

        return $data;
    }
}
