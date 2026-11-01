<?php

namespace App\Concerns;

trait AuthorizesModule
{
    protected function authorizeModule(string $module, string $action = 'access'): void
    {
        abort_unless(
            auth()->user()?->canModule($module, $action) ?? false,
            403,
            __('You do not have permission to perform this action.'),
        );
    }
}
