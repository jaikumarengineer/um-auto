<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Modules\Base\Http\Controllers\BaseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bases', BaseController::class)->names('base');
});

Route::group([
    'prefix' => config('theme.admin_route_prefix', 'admin'),
    'as' => 'admin::',
], function () {

    Route::middleware(['admin.redirect'])->group(function () {

        Volt::route('login', 'admin.auth.login')->name('login');

    });


    Route::group([
        'middleware' => ['admin.check'],
    ], function () {

        // Dashboard
        Volt::route('dashboard', 'admin.dashboard.main-dashboard')->name('dashboard');

        // Users
        Route::group([
            'prefix' => 'users',
            'as' => 'users.',
        ], function () {

            Volt::route('list', 'admin.users.users-list-page')->name('list');
            Volt::route('create', 'admin.users.users-add-edit-page')->name('create');
            Volt::route('edit/{user_id}', 'admin.users.users-add-edit-page')->name('edit');
        });

        // Profile
        Volt::route('profile', 'admin.settings.profile-page')->name('profile');

        // Settings
        Route::group([
            'prefix' => 'settings',
            'as' => 'settings.',
        ], function () {

            Volt::route('general', 'admin.settings.general-setting-page')->name('general');
            Volt::route('website', 'admin.settings.website-setting-page')->name('website');
            Route::group([
                'prefix' => 'scripts',
                'as' => 'scripts.',
            ], function () {

                Volt::route('third-party', 'admin.settings.scripts.third-party-script-page')->name('third-party');
                Volt::route('custom', 'admin.settings.scripts.custom-script-page')->name('custom');
            });
        });

        // Documentation
        Route::group([
            'prefix' => 'documentation',
            'as' => 'documentation.',
        ], function () {

            Volt::route('help', 'admin.documentation.help-page')->name('help');
            Volt::route('changelog', 'admin.documentation.document-page')->name('changelog');
        });
    });
});
