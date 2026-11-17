<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * جدول api_key_origins (یک به چند): دامنه‌های مجاز کلید عمومی.
 * origin یکتاست: هر دامنه فقط مال یک کلید (در کل سیستم). با باطل‌شدن کلید ردیف‌هایش حذف می‌شوند.
 */
class ApiKeyOrigin extends Model
{
    protected $table = 'api_key_origins';

    public const UPDATED_AT = null;

    protected $fillable = ['api_key_id', 'origin'];

    protected $casts = ['api_key_id' => 'integer'];
}
