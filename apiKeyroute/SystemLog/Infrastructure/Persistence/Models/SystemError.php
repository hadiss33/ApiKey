<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Domain\Error\ErrorIdentity;
use App\Services\SystemLog\Domain\Error\ErrorStatus;
use App\Services\SystemLog\Domain\Error\LogLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * یک «خطای یکتا» (fingerprint). تکرار = شمارنده + جایگزینی عکس آخرین وقوع؛ دائمی؛ created_at = اولین دیده شدن.
 * source («کجا») و type («چه نوعی») شناسه‌ی دسته در system_log_categories هستند؛ level/status/actor_type عدد enum.
 * stack و details سنگین‌اند: در لیست select نشوند (LIST_COLUMNS).
 */
class SystemError extends Model
{
    /** ستون‌های لیست (بدون stack و details) */
    public const LIST_COLUMNS = [
        'id', 'branch_id', 'exception_class', 'type', 'level', 'code', 'message', 'file', 'line',
        'status', 'note', 'resolved_by', 'resolved_at', 'notified_at', 'occurrences', 'created_at', 'last_seen_at',
        'source', 'location', 'status_code', 'actor_type', 'actor_id', 'ip',
    ];

    protected $table = 'system_errors';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'fingerprint', 'exception_class', 'type', 'level', 'code', 'message', 'file', 'line',
        'status', 'note', 'resolved_by', 'resolved_at', 'notified_at', 'occurrences', 'created_at', 'last_seen_at',
        'source', 'location', 'status_code', 'actor_type', 'actor_id', 'ip', 'stack', 'details',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'type' => 'integer',
        'level' => 'integer',
        'status' => 'integer',
        'source' => 'integer',
        'line' => 'integer',
        'occurrences' => 'integer',
        'status_code' => 'integer',
        'actor_type' => 'integer',
        'actor_id' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'details' => 'array',
    ];

    /** تبدیل به DTO؛ stack و details فقط با $full (جزئیات) */
    public function toRecord(bool $full = false): ErrorRecord
    {
        return new ErrorRecord(
            id: $this->id,
            ref: ErrorIdentity::ref($this->id),
            branchId: $this->branch_id,
            exceptionClass: $this->exception_class,
            type: $this->type,
            level: LogLevel::from($this->level),
            code: $this->code,
            message: (string) $this->message,
            file: $this->file,
            line: $this->line,
            status: ErrorStatus::from($this->status),
            note: $this->note,
            resolvedBy: $this->resolved_by,
            resolvedAt: $this->resolved_at,
            notifiedAt: $this->notified_at,
            occurrences: $this->occurrences,
            firstSeenAt: $this->created_at,
            lastSeenAt: $this->last_seen_at,
            source: $this->source,
            location: $this->location,
            statusCode: $this->status_code,
            actorType: $this->actor_type,
            actorId: $this->actor_id,
            ip: $this->ip,
            stack: $full ? $this->stack : null,
            details: $full ? $this->details : null,
        );
    }
}
