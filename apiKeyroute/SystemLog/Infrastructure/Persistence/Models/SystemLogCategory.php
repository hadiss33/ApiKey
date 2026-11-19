<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * دسته‌ی خطا: kind = source («کجا») | type («چه نوعی»، با classes) | all («همه‌ی دسته‌ها» برای دسترسی).
 * system_errors.source/type و جدول واسط دسترسی به id اینجا اشاره می‌کنند. نام‌های نمایشی قابل تعیین از پنل‌اند.
 */
class SystemLogCategory extends Model
{
    protected $table = 'system_log_categories';

    public $timestamps = false;

    protected $fillable = ['kind', 'key', 'title_fa', 'title_en', 'classes'];

    protected $casts = ['id' => 'integer', 'classes' => 'array'];

    /** @return array{id: int, kind: string, key: string, title_fa: ?string, title_en: ?string, classes: ?array} */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'key' => $this->key,
            'title_fa' => $this->title_fa,
            'title_en' => $this->title_en,
            'classes' => $this->classes,
        ];
    }
}
