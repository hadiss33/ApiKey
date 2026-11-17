<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Helpers\SecurityHelper;
use App\Services\ApiKey\Application\Contracts\PathSecurity;

/** همان بررسی امنیت مسیر که IpTrust می‌کرد (مسیرهای مشکوک و IP بن‌شده). */
final class HelperPathSecurity implements PathSecurity
{
    public function allowed(string $path): bool
    {
        return SecurityHelper::checkPathSecurity($path);
    }
}
