<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOnly
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return to_route('admin::login')->with('error', __('Please sign in again to continue.'));
        }

        if (! $user->isUnrestrictedAdmin()) {
            abort(403, __('Only an admin can open settings and configuration.'));
        }

        return $next($request);
    }
}
