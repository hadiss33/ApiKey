<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** ردپای موقت دیباگ (کلید + step + آدرس + داده)؛ فقط retention_days روز نگه‌داری می‌شود. */
class SystemTrace extends Model
{
    protected $table = 'system_traces';

    public $timestamps = false;

    /** created_at از نوع DATETIME(3) است (ترتیب stepهای یک request با میلی‌ثانیه حفظ می‌شود) */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['trace_id', 'branch_id', 'key', 'step', 'address', 'data', 'created_at'];

    protected $casts = ['branch_id' => 'integer', 'data' => 'array', 'created_at' => 'datetime'];

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'trace_id' => $this->trace_id,
            'branch_id' => $this->branch_id,
            'key' => $this->key,
            'step' => $this->step,
            'address' => $this->address,
            'data' => $this->data,
            'created_at' => $this->created_at,
        ];
    }
}
