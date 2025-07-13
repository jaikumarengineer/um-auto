<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::group([
    'namespace' => 'App\Http\Livewire\Admin',
    'prefix'=>config('theme.admin_route_prefix','admin'),
    'as'=>'admin::',
],function(){

    Route::middleware(['admin.redirect'])->group(function () {

        Volt::route('login','admin.auth.login')->name('login');

    });

    Route::group([
        'middleware'=>['admin.check'],
    ],function(){

        Volt::route('dashboard','admin.dashboard.main-dashboard')->name('dashboard');

        Route::group([
            'prefix'=>'settings',
            'as'=>'settings.',
        ],function(){

            Volt::route('general','admin.settings.general-setting-page')->name('general');
            Volt::route('website','admin.settings.website-setting-page')->name('website');

            Route::group([
                'prefix'=>'scripts',
                'as'=>'scripts.',
            ],function(){

                Volt::route('third-party','admin.settings.scripts.third-party-script-page')->name('third-party');
                Volt::route('custom','admin.settings.scripts.custom-script-page')->name('custom');

            });

        });

    });

});
