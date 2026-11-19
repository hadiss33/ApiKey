<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

/** تعیین دسته و نام نمایشی یک نوع رویداد (فقط مدیر). */
class UpdateTypeRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{1,31}$/'],
            'title_fa' => ['nullable', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
        ];
    }
}
