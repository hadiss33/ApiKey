<?php

namespace App\Services\ApiKey\Presentation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * اعتبارسنجی شکل ورودی دسته‌ها (store / update). وجود prefix در درخت، «هرگز» و تکراری‌بودن در CategoryRegistry.
 * خطا با قالب error (کد 4220، HTTP 422).
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // دسترسی (اپراتور ادمین شعبه) در PanelGuard
    }

    public function rules(): array
    {
        $create = $this->route()?->getActionMethod() === 'store';
        $required = $create ? 'required' : 'sometimes';
        $max = max(1, (int) config('api_key.max_rules_per_category', 30));

        return [
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'is_active' => [$create ? 'prohibited' : 'sometimes', 'boolean'],
            'rules' => [$required, 'array', 'min:1', 'max:' . $max],
            'rules.*' => ['array'],
            'rules.*.prefix' => ['required', 'string', 'max:200'],
            'rules.*.level' => ['required', 'string', 'in:read,write'],
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
