<?php

use App\Facades\Setting;

if (! function_exists('setting')) {
    /**
     * Get a setting value, or set many values when an array is passed.
     *
     * @param  string|array<string, mixed>|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function setting($key = null, $default = null, string $group = 'general')
    {
        if (is_array($key)) {
            Setting::setMany($key, is_string($default) ? $default : $group);

            return null;
        }

        if ($key === null) {
            return Setting::all();
        }

        return Setting::get($key, $default, $group);
    }
}
