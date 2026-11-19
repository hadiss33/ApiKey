<?php

namespace App\Services\ApiKey\Application\Contracts;

/**
 * پورت «فهرست prefix های قابل انتخاب»: درخت route های ثبت‌شده در روتر (نه config دستی).
 * پیاده‌سازی: RouteTree. هم پنل (انتخاب prefix) و هم اعتبارسنجی قانون‌های دسته از همین استفاده می‌کنند.
 */
interface RouteCatalog
{
    /**
     * @return array<int, array{prefix:string, segment:string, depth:int, read:int, write:int, children:array}>
     */
    public function tree(): array;

    /** آیا این prefix یکی از گره‌های درخت است؟ (بخش‌های پارامتری و مسیرهای «هرگز» گره نمی‌سازند) */
    public function has(string $prefix): bool;
}
