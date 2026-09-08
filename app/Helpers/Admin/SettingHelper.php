<?php

namespace App\Helpers\Admin;

use App\Facades\Setting;
use Illuminate\Support\Arr;

class SettingHelper
{
    /**
     * Upsert many settings for a group (arrays are stored as JSON/array type).
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOrUpdateSetting(array $data = [], string $group = 'general'): void
    {
        $pairs = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $pairs[(string) $key] = array_values($value);
            } else {
                $pairs[(string) $key] = $value;
            }
        }

        Setting::setMany($pairs, $group);
    }

    /**
     * Load settings by keys with optional defaults for missing entries.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function getByKeys(array $keys, string $group = 'general', array $defaults = []): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = Setting::get($key, $defaults[$key] ?? null, $group);
        }

        foreach ($defaults as $key => $value) {
            if (! Arr::has($result, $key) || $result[$key] === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
