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

        // Session expiry during a Livewire update used to hard-403 the whole page.
        // Send guests back to login instead so "Add vendor" doesn't look randomly broken.
        if (! $user) {
            return to_route('admin::login')->with('error', __('Please sign in again to continue.'));
        }

        if ($user->isUnrestrictedAdmin()) {
            return $next($request);
        }

        $allowed = false;

        foreach ($roles as $role) {
            $allowed = match ($role) {
                'master' => $user->isManagerLike()
                    || $user->canAnyModule(['dashboard', 'users', 'grouping']),
                'purchase' => $user->isPurchase()
                    || $user->canAnyModule(['quality.purchase.orders', 'quality.purchase.invoices']),
                'incoming' => $user->isIncoming()
                    || $user->canAnyModule([
                        'quality.incoming.parts',
                        'quality.incoming.standards',
                        'quality.incoming.sampling',
                        'quality.incoming.reports',
                        'quality.incoming.vendors',
                        'quality.incoming.categories',
                    ]),
                'manager' => $user->isManagerLike(),
                default => false,
            };

            if ($allowed) {
                break;
            }
        }

        // Shared analysis / alerts for either department
        if (! $allowed && in_array('purchase', $roles, true) && in_array('incoming', $roles, true)) {
            $allowed = $user->canAnyModule(['quality.analysis', 'quality.alerts'])
                || $user->isPurchase()
                || $user->isIncoming()
                || $user->isManagerLike();
        }

        if (! $allowed) {
            abort(403, __('You do not have access to this quality screen.'));
        }

        return $next($request);
    }
}
