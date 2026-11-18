<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جدول api_key_category_rules: هر ردیف یک قانون «prefix → سطح» در یک دسته. */
class ApiKeyCategoryRule extends Model
{
    protected $table = 'api_key_category_rules';

    protected $fillable = ['category_id', 'prefix', 'level'];

    protected $casts = [
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ApiKeyCategory::class, 'category_id');
    }
}
