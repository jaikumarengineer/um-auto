<?php

use Illuminate\Support\Facades\Route;
use Modules\Theme\Http\Controllers\ThemeController;

Route::middleware(['web'])->group(function () {
    Route::redirect('/', '/'.trim(config('theme.admin_route_prefix', 'admin'), '/').'/login')
        ->name('home');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('themes', ThemeController::class)->names('theme');
});
