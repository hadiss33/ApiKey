<?php

namespace App\Http\Middleware;

use App\Services\RecaptchaService;
use Closure;
use Illuminate\Http\Request;

class VerifyRecaptcha
{
    public function __construct(protected RecaptchaService $recaptcha) {}

    public function handle(Request $request, Closure $next)
    {
        $token  = $request->header('X-Recaptcha-Token');
        $result = $this->recaptcha->verify($token, $request->ip());

        // if (!$result['valid']) {
        //     return response()->json([
        //         'message'              => __('recaptcha.verification_failed'),
        //         'recaptcha_error_code' => $result['error_code'],
        //     ], 428);
        // }

        return $next($request);
    }
}
