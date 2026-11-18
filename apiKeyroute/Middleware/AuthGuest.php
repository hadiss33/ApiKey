<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;


class AuthGuest
{

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            return app(Pipeline::class)
                ->send($request)
                ->through([
                    \App\Http\Middleware\AuthWithJWT::class,
                ])
                ->then(function ($request) use ($next) {
                    return $next($request);
                });
        }

        $request->attributes->add([
            'group' => 'b2c',
            'level' => 1,
        ]);

        return $next($request);
    }
}
