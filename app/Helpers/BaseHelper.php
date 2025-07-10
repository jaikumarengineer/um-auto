<?php

use App\Models\User;
use Illuminate\Support\Str;

if (!function_exists('generateUserName')) {
    function generateUserName($name):string
    {
        $baseUsername = Str::slug($name); // e.g., "John Doe" -> "john-doe"
        do
        {
            $username = $baseUsername . rand(10000000, 99999999);
        }
        while (User::where('username', $username)->exists());
        return $username;
    }
}
