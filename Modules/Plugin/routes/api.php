<?php

use Illuminate\Support\Facades\Route;
use Modules\Plugin\Http\Controllers\PluginController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('plugins', PluginController::class)->names('plugin');
});
