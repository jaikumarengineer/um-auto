<?php

use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\FileUploadController;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Middleware\AdminLayoutMiddleware;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cores', CoreController::class)->names('core');
});

Route::group([
    'prefix' => config('theme.admin_route_prefix', 'admin'),
    'as' => 'admin::',
    'middleware' => [
        AdminLayoutMiddleware::class,
    ],
], function () {

    Route::middleware(['admin.redirect'])->group(function () {

        Route::livewire('/', 'admin.auth.login')->name('main');
        Route::livewire('login', 'admin.auth.login')->name('login');

    });

    Route::group([
        'middleware' => ['admin.check'],
    ], function () {

        Route::livewire('profile', 'admin.settings.profile-page')->name('profile');

        // FilePond uploads (any authenticated admin: master / purchase / incoming)
        Route::post('file-upload', [FileUploadController::class, 'upload'])->name('file-upload');
        Route::delete('file-upload', [FileUploadController::class, 'revert'])->name('file-upload.revert');

        Route::middleware(['quality.role:master'])->group(function () {

            // Dashboard
            Route::livewire('dashboard', 'admin.dashboard.main-dashboard')->name('dashboard');

            // CKEditor 4 image upload
            Route::post('editor/upload', EditorUploadController::class)->name('editor.upload');

            // Users
            Route::group([
                'prefix' => 'users',
                'as' => 'users.',
            ], function () {

                Route::livewire('list', 'admin.users.users-list-page')->name('list');
                Route::livewire('create', 'admin.users.users-add-edit-page')->name('create');
                Route::livewire('edit/{user_id}', 'admin.users.users-add-edit-page')->name('edit');
            });

            Route::group([
                'prefix' => 'grouping',
                'as' => 'grouping.',
            ], function () {

                Route::livewire('list', 'admin.grouping.country-list-page')->name('list');

            });

            // Settings
            Route::group([
                'prefix' => 'settings',
                'as' => 'settings.',
            ], function () {

                Route::livewire('general', 'admin.settings.general-setting-page')->name('general');
                Route::livewire('website', 'admin.settings.website-setting-page')->name('website');
                Route::livewire('mail', 'admin.settings.mail-setting-page')->name('mail');
                Route::livewire('email-alerts', 'admin.settings.email-alerts-page')->name('email-alerts');
                Route::livewire('logs', 'admin.settings.server-log-page')->name('logs');
                Route::livewire('server-info', 'admin.settings.server-info-page')->name('server-info');
                Route::group([
                    'prefix' => 'scripts',
                    'as' => 'scripts.',
                ], function () {

                    Route::livewire('third-party', 'admin.settings.scripts.third-party-script-page')->name('third-party');
                    Route::livewire('custom', 'admin.settings.scripts.custom-script-page')->name('custom');
                });
            });

            // UI components demo
            Route::group([
                'prefix' => 'components',
                'as' => 'components.',
            ], function () {
                Route::livewire('forms', 'admin.components.forms-demo-page')->name('forms');
            });

            // Documentation
            Route::group([
                'prefix' => 'documentation',
                'as' => 'documentation.',
            ], function () {

                Route::livewire('help', 'admin.documentation.help-page')->name('help');
                Route::livewire('changelog', 'admin.documentation.document-page')->name('changelog');
            });
        });
    });
});
