<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * جدول api_keys. کلید خام هرگز ذخیره نمی‌شود (فقط key_hash). ردیف حذف فیزیکی نمی‌شود (باطل = revoked_at)
 * تا رکوردهایی که id این کلید را به‌عنوان هویت ثبت کرده‌اند همیشه قابل نمایش باشند.
 */
class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'branch_id', 'name', 'description', 'type', 'environment', 'prefix',
        'key_hash', 'previous_key_hash', 'previous_key_expires_at',
        'allowed_ips', 'rate_limit_per_minute', 'expires_at',
        'last_used_at', 'last_used_ip',
        'created_by', 'updated_by', 'rotated_at', 'revoked_at', 'revoked_by',
    ];

    protected $hidden = ['key_hash', 'previous_key_hash'];

    protected $casts = [
        'branch_id' => 'integer',
        'type' => 'integer',
        'environment' => 'integer',
        'allowed_ips' => 'array',
        'rate_limit_per_minute' => 'integer',
        'previous_key_expires_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'revoked_by' => 'integer',
    ];

    /** ردیف‌های جدول رابط (دسته و سطح؛ شناسه‌ی دسته از config) */
    public function grantRows(): HasMany
    {
        return $this->hasMany(ApiKeyGrant::class, 'api_key_id');
    }

    public function origins(): HasMany
    {
        return $this->hasMany(ApiKeyOrigin::class, 'api_key_id');
    }

    /** نیازمند بارگذاری grantRows و origins */
    public function toRecord(): ApiKeyRecord
    {
        return new ApiKeyRecord(
            id: (int) $this->id,
            branchId: (int) $this->branch_id,
            name: (string) $this->name,
            description: $this->description,
            type: (int) $this->type,
            environment: (int) $this->environment,
            prefix: (string) $this->prefix,
            keyHash: (string) $this->key_hash,
            previousKeyHash: $this->previous_key_hash,
            previousKeyExpiresAt: $this->previous_key_expires_at,
            grants: $this->grantRows
                ->groupBy('category_id')
                ->map(fn ($rows) => $rows->pluck('level')->map(fn ($l) => (int) $l)->sort()->values()->all())
                ->sortKeys()
                ->all(),
            allowedIps: array_values((array) ($this->allowed_ips ?? [])),
            origins: $this->origins->pluck('origin')->sort()->values()->all(),
            rateLimitPerMinute: $this->rate_limit_per_minute,
            expiresAt: $this->expires_at,
            lastUsedAt: $this->last_used_at,
            lastUsedIp: $this->last_used_ip,
            createdBy: (int) $this->created_by,
            updatedBy: $this->updated_by,
            rotatedAt: $this->rotated_at,
            revokedAt: $this->revoked_at,
            revokedBy: $this->revoked_by,
            createdAt: $this->created_at,
        );
    }
}
