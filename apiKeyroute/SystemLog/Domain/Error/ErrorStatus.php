<?php

namespace App\Services\SystemLog\Domain\Error;

/**
 * وضعیت رسیدگی به یک خطا؛ مقدار عددی در ستون tinyint (system_errors.status) ذخیره می‌شود.
 * خطای Resolved که دوباره رخ دهد به New برمی‌گردد؛ خطای Ignored اعلان نمی‌شود.
 */
enum ErrorStatus: int
{
    case New = 1;
    case Investigating = 2;
    case Resolved = 3;
    case Ignored = 4;

    public function key(): string
    {
        return strtolower($this->name);
    }
}
