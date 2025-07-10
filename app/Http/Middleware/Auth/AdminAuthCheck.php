<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (Auth::user()->isAdmin())
            {
                return $next($request);
            }

            return to_route(config('theme.auth_dashboard_route'))
                ->with('error', 'You do not have admin access.');
        }

        return to_route('admin::login')->with('error', 'You do not have admin access.');
    }
}
