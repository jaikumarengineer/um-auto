<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CoderOne Admin Route Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix for the admin routes in the CoderOne application.
    | It is used to group all admin-related routes under a common prefix.
    |
    */

    'admin_route_prefix' => env('ADMIN_ROUTE_PREFIX', 'admin'),

    'home_route' => 'home',
    'auth_login_route' => 'login',
    'auth_dashboard_route' => 'dashboard',

];
