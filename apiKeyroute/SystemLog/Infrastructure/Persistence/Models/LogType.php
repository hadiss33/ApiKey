<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع رویداد لاگ فعالیت (داده، نه کد): code یکتا؛ category و نام‌های نمایشی از پنل تعیین می‌شوند. */
class LogType extends Model
{
    protected $table = 'system_log_types';

    public $timestamps = false;

    protected $fillable = ['code', 'category', 'title_fa', 'title_en'];

    protected $casts = ['id' => 'integer'];

    public function logs(): HasMany
    {
        return $this->hasMany(SystemLog::class, 'type_id');
    }

    /** @return array{id: int, code: string, category: string, title_fa: ?string, title_en: ?string} */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'category' => $this->category,
            'title_fa' => $this->title_fa,
            'title_en' => $this->title_en,
        ];
    }
}
