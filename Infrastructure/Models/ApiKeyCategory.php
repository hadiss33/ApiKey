<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** جدول api_key_categories: دسته‌های هر شعبه. مدیر می‌سازد و ویرایش می‌کند. */
class ApiKeyCategory extends Model
{
    protected $table = 'api_key_categories';

    protected $fillable = ['branch_id', 'name', 'is_active', 'created_by'];

    protected $casts = [
        'branch_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(ApiKeyCategoryRule::class, 'category_id');
    }

    public function categoryKeys(): HasMany
    {
        return $this->hasMany(ApiKeyCategoryKey::class, 'category_id');
    }
}
