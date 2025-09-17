<?php

use Illuminate\Support\Facades\Route;
use Modules\Theme\Http\Controllers\ThemeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('themes', ThemeController::class)->names('theme');
});
