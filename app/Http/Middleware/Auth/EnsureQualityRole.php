<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQualityRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->isMaster()) {
            return $next($request);
        }

        $allowed = match (true) {
            $user->isPurchase() => in_array('purchase', $roles, true),
            $user->isIncoming() => in_array('incoming', $roles, true),
            default => false,
        };

        if (! $allowed) {
            abort(403);
        }

        return $next($request);
    }
}
