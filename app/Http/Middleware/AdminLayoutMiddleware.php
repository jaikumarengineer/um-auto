<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class AdminLayoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->segment(1) == config('theme.admin_route_prefix', 'admin')) {
            Config::set(
                'livewire.layout',
                'base::components.layouts.admin.app'
            );
        }

        return $next($request);
    }
}
