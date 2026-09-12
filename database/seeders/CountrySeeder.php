<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CountrySeeder extends Seeder
{
    /**
     * Seed countries from database/data/countries.json
     * (extracted from the legacy db_backup.sql dump).
     */
    public function run(): void
    {
        $path = database_path('data/countries.json');

        if (! File::exists($path)) {
            $this->command?->error("Missing countries data file: {$path}");

            return;
        }

        /** @var list<array<string, mixed>> $countries */
        $countries = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $now = now();

        $rows = array_map(static function (array $country) use ($now): array {
            return [
                'iso' => $country['iso'],
                'name' => $country['name'],
                'nicename' => $country['nicename'],
                'iso3' => $country['iso3'],
                'numcode' => $country['numcode'],
                'phonecode' => $country['phonecode'],
                'region_id' => $country['region_id'],
                'timezone' => $country['timezone'],
                'utcname' => $country['utcname'],
                'utc' => $country['utc'],
                'abbreviations' => $country['abbreviations'],
                'status' => (bool) ($country['status'] ?? true),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $countries);

        // Upsert by ISO so re-running the seeder is safe.
        foreach (array_chunk($rows, 100) as $chunk) {
            Country::query()->upsert(
                $chunk,
                uniqueBy: ['iso'],
                update: [
                    'name',
                    'nicename',
                    'iso3',
                    'numcode',
                    'phonecode',
                    'region_id',
                    'timezone',
                    'utcname',
                    'utc',
                    'abbreviations',
                    'status',
                    'updated_at',
                ],
            );
        }

        $this->command?->info('Seeded '.count($rows).' countries.');
    }
}
