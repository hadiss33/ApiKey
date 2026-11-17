<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Http\Request;

/**
 * کلید API درخواست، فقط از هدر (هرگز از URL/body): X-Api-Key، یا Authorization: Bearer با پیشوند کلید ما (erp_sk_/erp_pk_).
 * مقدار X-Api-Key بیرونی (پیشوند دیگر) کلید ما حساب نمی‌شود، تا درخواست‌های بی‌ربط مسیر کلید را فعال نکنند.
 * توکن JWT (Bearer بدون پیشوند کلید) هم کلید نیست.
 */
final class ApiKeyHeader
{
    public static function extract(Request $request): ?string
    {
        $prefixes = (array) config('api_key.prefixes', []);

        foreach ([trim((string) $request->headers->get('X-Api-Key', '')), (string) $request->bearerToken()] as $candidate) {
            if ($candidate !== '' && ApiKeyRules::looksLikeKey($candidate, $prefixes)) {
                return $candidate;
            }
        }

        return null;
    }
}
