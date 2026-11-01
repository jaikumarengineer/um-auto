<?php

namespace App\Concerns;

use App\Support\PermissionCatalog;

/**
 * Livewire helpers for the shared permission matrix (grantedPermissions property).
 */
trait ManagesGrantedPermissions
{
    public function enableAllPermissions(): void
    {
        $this->grantedPermissions = PermissionCatalog::allPermissionNames();
    }

    public function disableAllPermissions(): void
    {
        $this->grantedPermissions = [];
    }

    public function viewOnlyPermissions(): void
    {
        $this->grantedPermissions = PermissionCatalog::viewOnlyPermissionNames();
    }

    public function toggleGrantedPermission(string $name): void
    {
        if (! in_array($name, PermissionCatalog::allPermissionNames(), true)) {
            return;
        }

        $current = collect($this->grantedPermissions);

        if ($current->contains($name)) {
            $this->grantedPermissions = $current
                ->reject(fn (string $item) => $item === $name)
                ->values()
                ->all();

            return;
        }

        $this->grantedPermissions = $current->push($name)->unique()->values()->all();
    }

    public function toggleRowPermissions(string $module): void
    {
        $names = collect(PermissionCatalog::actions())
            ->map(fn (string $action) => PermissionCatalog::name($module, $action))
            ->all();

        $current = collect($this->grantedPermissions);
        $allOn = collect($names)->every(fn (string $name) => $current->contains($name));

        if ($allOn) {
            $this->grantedPermissions = $current->reject(fn (string $name) => in_array($name, $names, true))->values()->all();

            return;
        }

        $this->grantedPermissions = $current->merge($names)->unique()->values()->all();
    }

    public function toggleColumnPermissions(string $groupKey, string $action): void
    {
        $groups = PermissionCatalog::groups();
        $modules = $groups[$groupKey]['modules'] ?? [];
        $names = collect($modules)
            ->filter(fn (string $module) => array_key_exists($module, PermissionCatalog::modules()))
            ->map(fn (string $module) => PermissionCatalog::name($module, $action))
            ->all();

        $current = collect($this->grantedPermissions);
        $allOn = collect($names)->every(fn (string $name) => $current->contains($name));

        if ($allOn) {
            $this->grantedPermissions = $current->reject(fn (string $name) => in_array($name, $names, true))->values()->all();

            return;
        }

        $this->grantedPermissions = $current->merge($names)->unique()->values()->all();
    }

    /**
     * @param  list<string>  $names
     */
    protected function setGrantedPermissions(array $names): void
    {
        $this->grantedPermissions = array_values(array_intersect(
            PermissionCatalog::allPermissionNames(),
            $names,
        ));
    }
}
