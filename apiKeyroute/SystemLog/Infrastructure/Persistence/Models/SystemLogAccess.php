<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Domain\Access\Grant;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * دسترسی یک اپراتور به خطاها؛ یک ردیف به‌ازای هر اپراتور (operator_id یکتا).
 * دسته‌هایی که می‌بیند در جدول واسط system_log_access_categories است (دسته‌ی ویژه‌ی «all» = همه).
 * is_manager = مدیر (رکورد اول دستی با SQL). channel = تنها کانال اعلان (enum AlertChannel: sms | telegram | bale؛ null = بدون اعلان؛ ستون دیتابیس: channels از نوع ENUM).
 */
class SystemLogAccess extends Model
{
    protected $table = 'system_log_access';

    protected $fillable = ['operator_id', 'is_manager', 'is_active', 'channel', 'created_by', 'updated_by'];

    protected $casts = [
        'operator_id' => 'integer',
        'is_manager' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * کانال اعلان (AlertChannel) در ستون enum با نام `channels` ذخیره می‌شود (نام ستون را مایگریشن ۰۰۰۰۰۴ تعیین کرده).
     * مقدار نامعتبر/بازمانده‌ی قدیمی (مثلاً JSON) به‌جای exception همان «بدون کانال» (null) خوانده می‌شود.
     */
    protected function channel(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => AlertChannel::tryFrom((string) ($attributes['channels'] ?? '')),
            set: fn (?AlertChannel $channel) => ['channels' => $channel?->value],
        );
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SystemLogCategory::class, 'system_log_access_categories', 'access_id', 'category_id');
    }

    /** نیازمند بارگذاری رابطه‌ی categories */
    public function toGrant(): Grant
    {
        return new Grant(
            id: $this->id,
            operatorId: $this->operator_id,
            isManager: $this->is_manager,
            isActive: $this->is_active,
            all: $this->categories->contains('kind', ErrorTaxonomy::ALL),
            categoryIds: $this->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            channel: $this->channel,
        );
    }
}
