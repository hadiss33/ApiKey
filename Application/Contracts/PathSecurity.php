<?php

namespace App\Services\ApiKey\Application\Contracts;

/**
 * پورت «امنیت مسیر». IpTrust دو کار می‌کرد: اعتماد به IP، و بررسی امنیت مسیر (مسیرهای مشکوک/IP بن‌شده).
 * اولی را خود کلید (allowed_ips) جایگزین می‌کند؛ دومی باید بماند و اینجا است. پیاده‌سازی: HelperPathSecurity.
 */
interface PathSecurity
{
    public function allowed(string $path): bool;
}
