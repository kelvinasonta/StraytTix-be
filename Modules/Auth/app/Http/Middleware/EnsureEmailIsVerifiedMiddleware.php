<?php

namespace Modules\Auth\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\app\Exceptions\EmailVerificationRequiredException;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            throw new EmailVerificationRequiredException;
        }

        return $next($request);
    }
}
