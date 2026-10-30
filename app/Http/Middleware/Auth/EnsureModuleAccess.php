<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'access'): Response
    {
        $user = $request->user();

        if (! $user) {
            return to_route('admin::login')->with('error', __('Please sign in again to continue.'));
        }

        if (! $user->canModule($module, $action)) {
            abort(403, __('You do not have access to this screen.'));
        }

        return $next($request);
    }
}
