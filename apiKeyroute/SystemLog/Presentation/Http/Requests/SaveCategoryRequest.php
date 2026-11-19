<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

/** ساخت (POST) یا ویرایش نام‌ها (PATCH) یک دسته‌ی خطا (فقط مدیر). classes فقط برای kind=type. */
class SaveCategoryRequest extends SystemLogRequest
{
    public function rules(): array
    {
        $create = $this->isMethod('POST');

        return [
            'kind' => [$create ? 'required' : 'prohibited', 'string', 'in:source,type'],
            'key' => [$create ? 'required' : 'prohibited', 'string', 'regex:/^[a-z][a-z0-9_]{1,31}$/'],
            'title_fa' => ['nullable', 'string', 'max:80'],
            'title_en' => ['nullable', 'string', 'max:80'],
            'classes' => ['nullable', 'array'],
            'classes.*' => ['string', 'max:200'],
        ];
    }
}
