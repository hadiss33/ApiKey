<?php

namespace App\Services\ApiKey\Application\Contracts;

/**
 * پورت ذخیره‌ی دسته‌ها و قانون‌هایشان.
 * هر دسته متعلق به یک شعبه است و قانون‌های آن prefix → سطح هستند.
 */
interface CategoryStore
{
    /**
     * @return array<int, array{id:int, branch_id:int, name:string, is_active:bool, rules:array<int,array{prefix:string,level:string}>}>
     */
    public function listForBranch(int $branchId): array;

    /**
     * یک دسته با قانون‌هایش.
     * @return array{id:int, branch_id:int, name:string, is_active:bool, rules:array<int,array{prefix:string,level:string}>}|null
     */
    public function find(int $id, int $branchId): ?array;

    /**
     * همه‌ی دسته‌هایی که id آن‌ها در لیست است، با قانون‌هایشان (بدون فیلتر شعبه).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{id:int, branch_id:int, name:string, is_active:bool, rules:array<int,array{prefix:string,level:string}>}>
     */
    public function findMany(array $ids): array;

    /**
     * ساخت دسته‌ی جدید با قانون‌هایش.
     *
     * @param  array<int, array{prefix:string, level:string}>  $rules
     * @return array{id:int, branch_id:int, name:string, is_active:bool, rules:array<int,array{prefix:string,level:string}>}
     */
    public function create(int $branchId, string $name, array $rules, int $operatorId): array;

    /**
     * ویرایش دسته (name/is_active/rules). فقط فیلدهای داده‌شده تغییر می‌کنند.
     *
     * @param  array{name?:string, is_active?:bool, rules?:array<int,array{prefix:string,level:string}>}  $data
     * @return array{id:int, branch_id:int, name:string, is_active:bool, rules:array<int,array{prefix:string,level:string}>}
     */
    public function update(int $id, int $branchId, array $data): array;

    /** آیا این دسته به حداقل یک کلید وصل است؟ */
    public function hasKeys(int $id): bool;

    /** تعداد کلیدهای وصل‌شده به این دسته */
    public function keyCount(int $id): int;

    /** حذف فیزیکی؛ فراخوان باید قبلش hasKeys را بررسی کرده باشد */
    public function delete(int $id, int $branchId): void;
}
