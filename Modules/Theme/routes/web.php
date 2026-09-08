<?php

use Illuminate\Support\Facades\Route;
use Modules\Theme\Http\Controllers\ThemeController;

Route::middleware(['web'])->group(function () {
        Route::get('/', function () {
        return view('theme::home');
    })->name('home');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('themes', ThemeController::class)->names('theme');
});
