<?php

namespace App\Services\SystemLog\Domain\Error;

/** شدت خطا؛ مقدار عددی در ستون tinyint (system_errors.level) ذخیره می‌شود. */
enum LogLevel: int
{
    case Debug = 1;
    case Info = 2;
    case Warning = 3;
    case Error = 4;
    case Critical = 5;

    public function key(): string
    {
        return strtolower($this->name);
    }
}
