<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowedHostsMiddleware
{
    protected const ALLOWED_HOSTS = [
        'service01.ir',
        'api.service01.ir',
        'ipg.service01.ir',
        'mmah.ir',
        'smn24.ir',
        'svsh.ir',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!in_array(getDomain(), self::ALLOWED_HOSTS)) {
            abort(403);
        }

        return $next($request);
    }
}
