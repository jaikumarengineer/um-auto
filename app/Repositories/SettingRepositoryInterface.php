<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    public function findByGroupAndKey(string $group, string $key): ?Setting;

    public function getByGroup(string $group): Collection;

    public function allGrouped(): Collection;

    /**
     * @param  mixed  $value
     */
    public function upsert(string $group, string $key, $value, string $type = 'string'): Setting;

    public function deleteByGroupAndKey(string $group, string $key): bool;
}
