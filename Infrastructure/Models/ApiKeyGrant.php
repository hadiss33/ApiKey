<?php

namespace App\Services\ApiKey\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * جدول رابط api_key_grants (چند به چند): کلید ↔ (دسته، سطح).
 * دسته‌ها جدول ندارند؛ category_id همان کلید عددی config/api_key.php → categories است (بدون FK).
 * level: 1 = read، 2 = write (ApiKeyRules::LEVELS).
 */
class ApiKeyGrant extends Model
{
    protected $table = 'api_key_grants';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = ['api_key_id', 'category_id', 'level'];

    protected $casts = ['api_key_id' => 'integer', 'category_id' => 'integer', 'level' => 'integer'];
}
