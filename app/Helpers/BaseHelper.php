<?php

use App\Models\User;
use Illuminate\Support\Str;

if (! function_exists('generateUserName')) {
    function generateUserName($name): string
    {
        $baseUsername = Str::slug($name, '') ?: 'user'; // e.g., "Jai" -> "jai"
        $username = $baseUsername;
        $suffix = 1;

        // "jai" taken -> "jai1", "jai2", ...
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername.$suffix++;
        }

        return $username;
    }
}

if (! function_exists('update_env')) {
    /**
     * @param  array<string, mixed>  $data
     */
    function update_env(array $data = []): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        foreach ($data as $key => $value) {
            $value = Str::replace(' ', '', (string) $value);
            $key = (string) $key;

            if (preg_match("/^{$key}=.*/m", $contents)) {
                $contents = preg_replace(
                    "/^{$key}=.*/m",
                    $key.'='.$value,
                    $contents
                );
            } else {
                $contents .= PHP_EOL.$key.'='.$value;
            }
        }

        file_put_contents($path, $contents);
    }
}

if (! function_exists('setting_json_decode')) {
    /**
     * @return array<int|string, mixed>
     */
    function setting_json_decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

if (! function_exists('admin_breadcrumb_home')) {
    /**
     * @return array{label: string, route: string}
     */
    function admin_breadcrumb_home(): array
    {
        $user = auth()->user();

        return [
            'label' => $user?->isMaster() ? __('Dashboard') : __('Home'),
            'route' => $user?->adminHomeRoute() ?? 'admin::dashboard',
        ];
    }
}

if (! function_exists('module_can')) {
    /**
     * Gate check against a PermissionCatalog module + action.
     */
    function module_can(string $module, string $action = 'access'): bool
    {
        return auth()->user()?->canModule($module, $action) ?? false;
    }
}

if (! function_exists('module_permission')) {
    function module_permission(string $module, string $action = 'access'): string
    {
        return \App\Support\PermissionCatalog::name($module, $action);
    }
}

if (! function_exists('media_url')) {
    /**
     * Build a browser URL for a stored media path (uploads/..., storage/..., absolute, or data URI).
     */
    function media_url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:')
            || str_starts_with($path, '//')
        ) {
            return $path;
        }

        // Legacy Livewire public-disk paths were stored without the "storage/" prefix.
        if (
            ! str_starts_with($path, 'uploads/')
            && ! str_starts_with($path, 'storage/')
            && ! str_starts_with($path, '/')
        ) {
            $path = 'storage/'.ltrim($path, '/');
        }

        return asset(ltrim($path, '/'));
    }
}
