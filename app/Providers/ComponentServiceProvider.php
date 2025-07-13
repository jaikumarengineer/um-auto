<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ComponentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::component('admin.theme.sidebar', 'components.admin.theme.sidebar');
        Blade::component('admin.theme.header', 'components.admin.theme.header');
        Blade::component('admin.theme.footer', 'components.admin.theme.footer');
    }
}
