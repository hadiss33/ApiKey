<?php

namespace App\Services\ApiKey\Application\DTOs;

/**
 * «کلید آماده» — رکورد + قانون‌های جمع‌شده‌ی دسته‌ها + اپراتور پاک‌سازی‌شده؛ همه با هم کش می‌شوند.
 *
 * aggregatedRules: اجتماع همه‌ی دسته‌های متصل. prefix → حداکثر سطح (1=read, 2=write).
 *   مثال: دسته A: {api/v2/system-log → write}  +  دسته B: {api/v2/system-log → read} → {api/v2/system-log → write}
 *
 * sanitizedOperator: stdClass با همان ساختار operator اما بدون ستون‌های حساس و is_admin=0.
 *   شعبه از خود کلید آمده (نه از اپراتور سازنده).
 */
final class PreparedKey
{
    /**
     * @param  array<string, int>  $aggregatedRules  prefix → حداکثر سطح (LEVEL_READ|LEVEL_WRITE)
     */
    public function __construct(
        public readonly ApiKeyRecord $record,
        public readonly array $aggregatedRules,
        public readonly object $sanitizedOperator,
    ) {}
}
