<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * پایه‌ی Requestهای پنل SystemLog. دسترسی داخل سرویس‌ها (AccessService) بررسی می‌شود نه اینجا.
 * خطای اعتبارسنجی با قالب error (کد 4220، HTTP 422)؛ بدون این، هندلر کلی پروژه ValidationException را ۵۰۰ می‌کند.
 */
abstract class SystemLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 4220,
                'message' => __('system_log.api.invalid_input'),
                'fields' => $validator->errors()->toArray(),
            ],
            'meta' => ['timestamp' => time()],
        ], 422));
    }
}
