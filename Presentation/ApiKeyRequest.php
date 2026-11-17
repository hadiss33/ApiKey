<?php

namespace App\Services\ApiKey\Presentation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * اعتبارسنجی شکل ورودی پنل کلیدها؛ قوانین بر اساس متد کنترلر (store / update / rotate).
 * قانون‌های کسب‌وکار (دسترسی‌ها با config، یکتایی دامنه، سقف کلید، ...) در ApiKeyService.
 * خطا با قالب error (کد 4220، HTTP 422)؛ بدون این، هندلر کلی پروژه ValidationException را ۵۰۰ می‌کند.
 */
class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // دسترسی (اپراتور ادمین شعبه) در کنترلر
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'store' => $this->keyRules(true),
            'update' => $this->keyRules(false),
            'rotate' => ['grace_hours' => ['nullable', 'integer', 'min:0', 'max:' . (int) config('api_key.rotation.max_grace_hours', 168)]],
            default => [],
        };
    }

    private function keyRules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => [$create ? 'required' : 'prohibited', 'string', 'in:secret,public'],
            'environment' => [$create ? 'sometimes' : 'prohibited', 'string', 'in:live,test'],
            // {"system_log": ["read", "write"], ...}؛ وجود دسته و سطح و عمومی‌بودنش در CategoryRegistry
            'grants' => ['sometimes', 'array', 'max:100'],
            'grants.*' => ['array', 'max:2'],
            'grants.*.*' => ['string', 'in:read,write'],
            'preset' => ['sometimes', 'nullable', 'string', 'max:64'],
            'allowed_ips' => ['sometimes', 'nullable', 'array', 'max:50'],
            'allowed_ips.*' => ['string', 'max:64'],
            'origins' => ['sometimes', 'nullable', 'array', 'max:20'],
            'origins.*' => ['string', 'max:255'],
            'rate_limit_per_minute' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'no_expiry' => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 4220,
                'message' => __('api_key.errors.invalid_input'),
                'fields' => $validator->errors()->toArray(),
            ],
            'meta' => ['timestamp' => time()],
        ], 422));
    }
}
