<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\Response;

class ValidateShamsiDate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // فیلدهایی که باید تاریخ شمسی باشند
        $dateFields = ['date', 'start_date', 'end_date', 'to_date', 'from_date'];
        
        foreach ($dateFields as $field) {
            if ($request->has($field)) {
                $dateValue = $request->get($field);
                
                if (!$this->isValidShamsiDate($dateValue)) {
                    return response()->json([
                        'success' => false,
                        'message' => "فیلد {$field} باید تاریخ شمسی معتبر به فرمت YYYY-MM-DD باشد",
                        'field' => $field,
                        'received_value' => $dateValue,
                        'example' => '1404-05-27'
                    ], 422);
                }
            }
        }

        return $next($request);
    }

    /**
     * بررسی اعتبار تاریخ شمسی
     */
    private function isValidShamsiDate(string $date): bool
    {
        // بررسی فرمت کلی
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            // تلاش برای تبدیل تاریخ شمسی
            $jalali = Jalalian::fromFormat('Y-m-d', $date);
            
            // بررسی محدودیت‌های منطقی
            $year = $jalali->getYear();
            $month = $jalali->getMonth();
            $day = $jalali->getDay();
            
            // سال باید بین 1300 تا 1500 باشد
            if ($year < 1300 || $year > 1500) {
                return false;
            }
            
            // ماه باید بین 1 تا 12 باشد
            if ($month < 1 || $month > 12) {
                return false;
            }
            
            // روز باید معتبر باشد
            if ($day < 1 || $day > $jalali->getMonthDays()) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
