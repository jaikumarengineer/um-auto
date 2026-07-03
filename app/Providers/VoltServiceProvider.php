<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
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
        $paths = array_merge(
            config('livewire.view_path', [resource_path('views/livewire')]),
            [resource_path('views/pages')]
        );

        Volt::mount($paths);
    }
}
