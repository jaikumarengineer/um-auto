<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (Auth::user()->isAdmin()) {
                return to_route(Auth::user()->adminHomeRoute());
            }

            return to_route(config('theme.auth_dashboard_route'))->with('error', 'You are already logged in as an admin.');
        }

        return $next($request);
    }
}
