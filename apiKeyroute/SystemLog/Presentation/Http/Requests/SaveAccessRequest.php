<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

/**
 * تنظیم دسترسی یک اپراتور به خطاها (فقط مدیر). category_ids شناسه‌ی دسته‌هاست (از GET categories؛ دسته‌ی «all» = همه).
 * برای مدیر (is_manager) دسته‌ها نادیده گرفته می‌شود. channel: تنها کانال اعلانِ این کاربر (sms | telegram | bale؛ خالی = بدون اعلان).
 */
class SaveAccessRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return [
            'is_manager' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['required_unless:is_manager,1,true', 'array'],
            'category_ids.*' => ['integer', 'min:1'],
            'channel' => ['nullable', 'string', 'in:sms,telegram,bale'],
        ];
    }
}
