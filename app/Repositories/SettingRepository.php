<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingRepository implements SettingRepositoryInterface
{
    public function findByGroupAndKey(string $group, string $key): ?Setting
    {
        return Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();
    }

    public function getByGroup(string $group): Collection
    {
        return Setting::query()
            ->where('group', $group)
            ->orderBy('key')
            ->get();
    }

    public function allGrouped(): Collection
    {
        return Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');
    }

    public function upsert(string $group, string $key, $value, string $type = 'string'): Setting
    {
        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    public function deleteByGroupAndKey(string $group, string $key): bool
    {
        return (bool) Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->delete();
    }
}
