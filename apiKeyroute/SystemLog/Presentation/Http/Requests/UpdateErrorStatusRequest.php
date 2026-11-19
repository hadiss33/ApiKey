<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

class UpdateErrorStatusRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:new,investigating,resolved,ignored'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
