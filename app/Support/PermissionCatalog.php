<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Modules that can be granted to non-admin users.
     *
     * @return array<string, string>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'users' => 'Users',
            'grouping' => 'Grouping',
            'quality.analysis' => 'Quality analysis',
            'quality.alerts' => 'Quality alerts',
            'quality.purchase.orders' => 'Purchase orders',
            'quality.purchase.invoices' => 'Purchase invoices',
            'quality.incoming.vendors' => 'Approved vendors',
            'quality.incoming.categories' => 'List of category',
            'quality.incoming.parts' => 'Incoming parts',
            'quality.incoming.standards' => 'Inspection standards',
            'quality.incoming.sampling' => 'Sampling plans',
            'quality.incoming.reports' => 'Inspection reports',
            'quality.approvals' => 'Approvals',
        ];
    }

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return ['access', 'view', 'add', 'edit', 'delete'];
    }

    /**
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        $names = [];

        foreach (array_keys(self::modules()) as $module) {
            foreach (self::actions() as $action) {
                $names[] = self::name($module, $action);
            }
        }

        return $names;
    }

    public static function name(string $module, string $action): string
    {
        return $module.'.'.$action;
    }

    /**
     * Default permissions granted when a role is assigned.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $role): array
    {
        $all = self::allPermissionNames();

        return match ($role) {
            'manager' => $all,
            'purchase' => self::namesForModules([
                'quality.analysis',
                'quality.alerts',
                'quality.purchase.orders',
                'quality.purchase.invoices',
            ]),
            'incoming' => self::namesForModules([
                'quality.analysis',
                'quality.alerts',
                'quality.incoming.parts',
                'quality.incoming.standards',
                'quality.incoming.sampling',
                'quality.incoming.reports',
            ]),
            default => [],
        };
    }

    /**
     * Spatie role name mirrored from user_type.
     */
    public static function roleForUserType(string $userType): ?string
    {
        return match ($userType) {
            'admin' => 'admin',
            'manager', 'sub_admin', 'staff' => 'manager',
            'purchase' => 'purchase',
            'incoming' => 'incoming',
            default => null,
        };
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    public static function namesForModules(array $modules): array
    {
        $names = [];

        foreach ($modules as $module) {
            foreach (self::actions() as $action) {
                $names[] = self::name($module, $action);
            }
        }

        return $names;
    }
}
