<?php

namespace App\Providers;

use App\Repositories\SettingRepository;
use App\Repositories\SettingRepositoryInterface;
use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingRepositoryInterface::class, SettingRepository::class);

        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService($app->make(SettingRepositoryInterface::class));
        });

        $this->app->singleton('setting', function ($app) {
            return $app->make(SettingService::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
