<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

/** فیلترهای لیست خطاها؛ source_id و type_id شناسه‌ی دسته‌اند (از GET categories). فیلتر فقط محدودتر می‌کند. */
class ListErrorsRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:new,investigating,resolved,ignored'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'type_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:0'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'text' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
