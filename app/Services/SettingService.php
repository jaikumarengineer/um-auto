<?php

namespace App\Services;

use App\Repositories\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public const CACHE_KEY = 'app.settings.all';

    public function __construct(
        protected SettingRepositoryInterface $repository
    ) {}

    /**
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null, string $group = 'general')
    {
        $all = $this->all();

        return $all[$group][$key] ?? $default;
    }

    /**
     * @param  mixed  $value
     */
    public function set(string $key, $value, string $group = 'general', ?string $type = null): void
    {
        $type ??= $this->detectType($value);
        $this->repository->upsert($group, $key, $value, $type);
        $this->forgetCache();
    }

    /**
     * @param  array<string, mixed>  $pairs
     */
    public function setMany(array $pairs, string $group = 'general'): void
    {
        foreach ($pairs as $key => $value) {
            $this->repository->upsert($group, (string) $key, $value, $this->detectType($value));
        }

        $this->forgetCache();
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        return $this->all()[$group] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $grouped = [];

            foreach ($this->repository->allGrouped() as $group => $items) {
                foreach ($items as $item) {
                    $grouped[$group][$item->key] = $item->value;
                }
            }

            return $grouped;
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  mixed  $value
     */
    protected function detectType($value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'array',
            default => 'string',
        };
    }
}
