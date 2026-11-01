<?php

namespace App\Support;

use App\Models\Role;
use Spatie\Permission\Models\Permission;

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
     * Grouped modules for the permission matrix UI.
     *
     * @return array<string, array{label: string, modules: list<string>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'modules' => ['dashboard', 'users', 'grouping'],
            ],
            'quality' => [
                'label' => 'Quality Management',
                'modules' => ['quality.analysis', 'quality.alerts'],
            ],
            'procurement' => [
                'label' => 'Procurement',
                'modules' => [
                    'quality.purchase.orders',
                    'quality.purchase.invoices',
                    'quality.incoming.vendors',
                ],
            ],
            'inventory' => [
                'label' => 'Inventory',
                'modules' => [
                    'quality.incoming.parts',
                    'quality.incoming.categories',
                    'quality.incoming.sampling',
                    'quality.incoming.standards',
                ],
            ],
            'inspection' => [
                'label' => 'Inspection',
                'modules' => [
                    'quality.incoming.reports',
                    'quality.approvals',
                ],
            ],
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
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'access' => 'Access',
            'view' => 'View',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ];
    }

    /**
     * Human-readable label for a permission slug (e.g. users.view → View Users).
     */
    public static function humanLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        $action = array_pop($parts);
        $module = implode('.', $parts);
        $moduleLabel = self::modules()[$module] ?? str($module)->replace('.', ' ')->title()->toString();
        $actionLabel = match ($action) {
            'access' => 'Access',
            'view' => 'View',
            'add' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            default => str($action)->title()->toString(),
        };

        return $actionLabel.' '.$moduleLabel;
    }

    /**
     * Flat permission rows for a module.
     *
     * @return list<array{name: string, label: string}>
     */
    public static function modulePermissionRows(string $module): array
    {
        $rows = [];

        foreach (self::actions() as $action) {
            $name = self::name($module, $action);
            $rows[] = [
                'name' => $name,
                'label' => self::humanLabel($name),
            ];
        }

        return $rows;
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
     * Default permissions granted when a role is first seeded.
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
     * Current Spatie role permissions (DB), falling back to seeded defaults.
     *
     * @return list<string>
     */
    public static function permissionsForRole(string $role): array
    {
        if ($role === 'admin') {
            return [];
        }

        try {
            $model = Role::findByName($role, 'web');
            $names = $model->permissions->pluck('name')->all();

            if ($names !== []) {
                return array_values(array_intersect(self::allPermissionNames(), $names));
            }
        } catch (\Throwable) {
            // Role may not exist yet.
        }

        return self::defaultsFor($role);
    }

    /**
     * Human descriptions used when seeding roles.
     *
     * @return array<string, string>
     */
    public static function roleDescriptions(): array
    {
        return [
            'admin' => 'Unrestricted master administrator. Settings and full access.',
            'manager' => 'Operational manager with approval rights across Quality modules.',
            'purchase' => 'Purchase department access to orders, invoices, and analysis.',
            'incoming' => 'Incoming department access to parts, standards, sampling, and reports.',
        ];
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

    /**
     * View-only permissions (access + view) for every module.
     *
     * @return list<string>
     */
    public static function viewOnlyPermissionNames(): array
    {
        $names = [];

        foreach (array_keys(self::modules()) as $module) {
            $names[] = self::name($module, 'access');
            $names[] = self::name($module, 'view');
        }

        return $names;
    }

    public static function ensurePermissionsExist(): void
    {
        foreach (self::allPermissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
