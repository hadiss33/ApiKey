<?php

namespace App\Services\ApiKey\Application\Contracts;

/**
 * پورت «اپراتور سازنده‌ی کلید». وقتی کلید حاضر است، میدلور AuthWithJWT اجرا نمی‌شود و ApiKeyAuth همان کار را می‌کند:
 * ردیف اپراتور (با همان شکل ردیف جدول operators که AuthWithJWT می‌گذاشت) را برای attribute «operator» می‌دهد.
 * پیاده‌سازی: EloquentOperatorDirectory.
 */
interface OperatorDirectory
{
    /** ردیف اپراتور فعال (status=1)، یا null اگر نیست/غیرفعال است (کلیدش کار نمی‌کند) */
    public function activeOperator(int $id): ?object;
}
