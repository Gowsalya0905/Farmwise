<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCurrentPasswordSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user()?->fresh();
        $hash = $request->session()->get('auth_password_hash');
        if (! $user || ! is_string($hash) || ! hash_equals($user->getAuthPassword(), $hash)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException;
        }
        Auth::setUser($user);

        return $next($request);
    }
}
