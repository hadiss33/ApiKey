<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Models\Official\Operators\Operator;
use App\Services\ApiKey\Application\Contracts\OperatorDirectory;

/**
 * اپراتور سازنده‌ی کلید برای attribute «operator». شکل خروجی عمداً همان ردیف خام جدول operators است
 * (stdClass با ستون‌های خام، مثل DB::table('operators')->first() در AuthWithJWT)، تا کنترلرهای قدیمی
 * که $request->get('operator')->id / ->is_admin می‌خوانند بدون تغییر کار کنند.
 * شرط AuthWithJWT هم همان است: status == 1.
 */
final class EloquentOperatorDirectory implements OperatorDirectory
{
    public function activeOperator(int $id): ?object
    {
        $row = Operator::query()->withoutGlobalScopes()->whereKey($id)->first();

        return $row !== null && (int) $row->status === 1 ? (object) $row->getAttributes() : null;
    }
}
