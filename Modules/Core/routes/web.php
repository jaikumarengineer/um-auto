<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Middleware\AdminLayoutMiddleware;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cores', CoreController::class)->names('core');
});

Route::group([
    'prefix' => config('theme.admin_route_prefix', 'admin'),
    'as' => 'admin::',
    'middleware'=>[
        AdminLayoutMiddleware::class,
    ]
], function () {

    Route::middleware(['admin.redirect'])->group(function () {

        Route::livewire('/', 'core::admin.auth.login')->name('main');
        Route::livewire('login', 'core::admin.auth.login')->name('login');

    });


    Route::group([
        'middleware' => ['admin.check'],
    ], function () {

        // Dashboard
        Route::livewire('dashboard', 'core::admin.dashboard.main-dashboard')->name('dashboard');

        // Users
        Route::group([
            'prefix' => 'users',
            'as' => 'users.',
        ], function () {

            Route::livewire('list', 'core::admin.users.users-list-page')->name('list');
            Route::livewire('create', 'core::admin.users.users-add-edit-page')->name('create');
            Route::livewire('edit/{user_id}', 'core::admin.users.users-add-edit-page')->name('edit');
        });

        // Profile
        Route::livewire('profile', 'core::admin.settings.profile-page')->name('profile');

        // Settings
        Route::group([
            'prefix' => 'settings',
            'as' => 'settings.',
        ], function () {

            Route::livewire('general', 'core::admin.settings.general-setting-page')->name('general');
            Route::livewire('website', 'core::admin.settings.website-setting-page')->name('website');
            Route::group([
                'prefix' => 'scripts',
                'as' => 'scripts.',
            ], function () {

                Route::livewire('third-party', 'core::admin.settings.scripts.third-party-script-page')->name('third-party');
                Route::livewire('custom', 'core::admin.settings.scripts.custom-script-page')->name('custom');
            });
        });

        // Documentation
        Route::group([
            'prefix' => 'documentation',
            'as' => 'documentation.',
        ], function () {

            Route::livewire('help', 'core::admin.documentation.help-page')->name('help');
            Route::livewire('changelog', 'core::admin.documentation.document-page')->name('changelog');
        });
    });
});
