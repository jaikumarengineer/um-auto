<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        PermissionCatalog::ensurePermissionsExist();

        $descriptions = PermissionCatalog::roleDescriptions();

        foreach (['admin', 'manager', 'purchase', 'incoming'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->forceFill([
                'description' => $descriptions[$roleName] ?? null,
                'is_active' => true,
            ])->save();

            if ($roleName === 'admin') {
                $role->syncPermissions([]);
            } elseif ($role->permissions()->count() === 0) {
                $role->syncPermissions(PermissionCatalog::defaultsFor($roleName));
            }
        }

        User::query()->each(function (User $user) {
            $user->syncRoleDefaults(forcePermissions: $user->permissions()->count() === 0);
        });
    }
}
