<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionCatalog::allPermissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (['admin', 'manager', 'purchase', 'incoming'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($roleName === 'admin') {
                $role->syncPermissions([]);
            } else {
                $role->syncPermissions(PermissionCatalog::defaultsFor($roleName));
            }
        }

        User::query()->each(function (User $user) {
            $user->syncRoleDefaults(forcePermissions: $user->permissions()->count() === 0);
        });
    }
}
