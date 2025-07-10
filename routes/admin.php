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

    });

});
