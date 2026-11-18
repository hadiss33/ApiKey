<?php

namespace App\Http\Middleware;

use App\Helpers\SecurityHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityPathFilter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!SecurityHelper::checkPathSecurity($request->path())) {
            return response()->json([
                'status' => false,
                'error' => [
                    'code' => 1005,
                    'message' => 'Access denied - suspicious activity detected'
                ]
            ], 403);
        }

        return $next($request);
    }
}
