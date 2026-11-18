<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جدول api_key_category_keys: کلید ↔ دسته (بدون سطح؛ سطح در قانون‌های دسته است). */
class ApiKeyCategoryKey extends Model
{
    protected $table = 'api_key_category_keys';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = ['api_key_id', 'category_id'];

    protected $casts = [
        'api_key_id' => 'integer',
        'category_id' => 'integer',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ApiKeyCategory::class, 'category_id');
    }
}
