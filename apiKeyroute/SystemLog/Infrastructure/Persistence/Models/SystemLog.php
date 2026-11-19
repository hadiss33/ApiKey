<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use App\Models\Official\Operators\Operator;
use App\Services\SystemLog\Application\DTOs\LogRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** یک رویداد فعالیت؛ همیشه شعبه‌دار و دائمی. created_at همان زمان وقوع است (نه اجرای جاب). */
class SystemLog extends Model
{
    protected $table = 'system_logs';

    public $timestamps = false;

    /** ستون created_at از نوع DATETIME(3) است؛ بدون این، میلی‌ثانیه‌ی زمان وقوع هنگام ذخیره حذف می‌شد */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['branch_id', 'operator_id', 'type_id', 'subject_id', 'ip', 'user_agent', 'created_at'];

    protected $casts = [
        'branch_id' => 'integer',
        'operator_id' => 'integer',
        'type_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(LogType::class, 'type_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(SystemLogDetail::class, 'log_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    /** رابطه‌هایی که برای نمایش لازم‌اند (بدون N+1) */
    public function scopeWithDisplay(Builder $query): Builder
    {
        return $query->with(['type', 'operator:id,first_name,last_name,personnel_id', 'detail']);
    }

    /** نیازمند scopeWithDisplay */
    public function toRecord(): LogRecord
    {
        return new LogRecord(
            id: $this->id,
            type: (string) $this->type?->code,
            titleFa: $this->type?->title_fa,
            titleEn: $this->type?->title_en,
            category: (string) $this->type?->category,
            subjectId: $this->subject_id,
            branchId: $this->branch_id,
            operatorId: $this->operator_id,
            operatorFirstName: $this->operator?->first_name,
            operatorLastName: $this->operator?->last_name,
            operatorPersonnelId: $this->operator?->personnel_id,
            ip: $this->ip,
            userAgent: $this->user_agent,
            details: $this->detail?->details,
            createdAt: $this->created_at,
        );
    }
}
