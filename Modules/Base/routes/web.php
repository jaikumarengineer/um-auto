<?php

use Illuminate\Support\Facades\Route;
use Modules\Base\Http\Controllers\BaseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bases', BaseController::class)->names('base');
});
