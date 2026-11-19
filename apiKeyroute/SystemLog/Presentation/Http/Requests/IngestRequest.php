<?php

namespace App\Services\SystemLog\Presentation\Http\Requests;

/**
 * ورودی ارسال خطا/لاگ فعالیت از سیستم‌های بیرونی (با کلید API)؛ قوانین بر اساس متد IngestController.
 * شعبه همیشه شعبه‌ی خود کلید است (از attribute)، نه ورودی.
 */
class IngestRequest extends SystemLogRequest
{
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'reportErrors' => $this->errorRules(),
            'storeAudit' => [
                // بدون نقطه/فاصله؛ در ذخیره پیشوند ext_ می‌گیرد تا با نوع‌های داخلی سیستم قاطی/جعل نشود
                'type' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,43}$/'],
                'data' => ['nullable'],
                // یک شناسه یا حداکثر ۵۰ شناسه (هر شناسه یک ردیف لاگ)
                'goal' => is_array($this->input('goal')) ? ['array', 'max:50'] : ['nullable', 'string', 'max:64'],
                'goal.*' => ['nullable', 'string', 'max:64'],
            ],
            default => [],
        };
    }

    private function errorRules(): array
    {
        $max = max(1, (int) config('system_log.external.max_batch', 20));

        return [
            'errors' => ['required', 'array', 'min:1', 'max:' . $max],
            'errors.*.message' => ['required', 'string', 'max:1000'],
            'errors.*.level' => ['nullable', 'string', 'in:debug,info,warning,error,critical'],
            'errors.*.code' => ['nullable', 'string', 'max:60'],
            'errors.*.file' => ['nullable', 'string', 'max:300'],
            'errors.*.line' => ['nullable', 'integer', 'min:0'],
            'errors.*.url' => ['nullable', 'string', 'max:300'],
            'errors.*.stack' => ['nullable', 'string', 'max:' . max(100, (int) config('system_log.external.max_stack_length', 4000))],
            'errors.*.context' => ['nullable', 'array'],
        ];
    }
}
