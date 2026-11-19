<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** جزئیات JSON یک لاگ (یک‌به‌یک و اختیاری؛ کلید اصلی = log_id). */
class SystemLogDetail extends Model
{
    protected $table = 'system_log_details';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['log_id', 'details'];

    protected $casts = ['log_id' => 'integer', 'details' => 'array'];
}
