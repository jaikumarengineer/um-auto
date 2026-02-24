<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AdminLayoutMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('admin::*')) {
            Config::set('livewire.component_layout','core::layouts.app');
            // Config::set('livewire.layout', 'core::layouts.app');
        }

        return $next($request);
    }
}
