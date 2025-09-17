<?php

use Illuminate\Support\Facades\Route;
use Modules\Plugin\Http\Controllers\PluginController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('plugins', PluginController::class)->names('plugin');
});
