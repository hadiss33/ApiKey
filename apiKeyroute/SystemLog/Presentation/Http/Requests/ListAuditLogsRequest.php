<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

class ListAuditLogsRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:48'],
            'category' => ['nullable', 'string', 'max:32'],
            'subject_id' => ['nullable', 'string', 'max:64'],
            'operator_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
