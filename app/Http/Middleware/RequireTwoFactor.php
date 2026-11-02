<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users whose role can see salaries or identifiers must enrol TOTP and pass a
 * challenge once per session before reaching the workspace.
 */
class RequireTwoFactor
{
    public const SESSION_KEY = 'two_factor_passed_for';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! config('roster.require_two_factor') || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get(self::SESSION_KEY) !== $user->id) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
