<?php

namespace Modules\Quality\Support;

use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionStandard;
use Modules\Quality\Models\SamplingPlan;

/**
 * C=0 sample sizes from work instruction ML-08.
 * A star means the whole lot is inspected. The accept number is always zero.
 */
class Ml08SamplingPlan
{
    public const STANDARD = 'ML-08';

    public const LEVEL = 'C=0';

    public const OPEN_LOT = 999999999;

    /** @var list<string> */
    public const AQLS = [
        '0.01', '0.015', '0.025', '0.04', '0.065', '0.1', '0.15', '0.25',
        '0.4', '0.65', '1', '1.5', '2.5', '4', '6.5', '10',
    ];

    /**
     * Lot min, lot max (null = and over), then one sample per AQL.
     * '*' means inspect the entire lot.
     *
     * @var list<array{0: int, 1: int|null, 2: list<int|string>}>
     */
    private const BANDS = [
        [2, 8, ['*', '*', '*', '*', '*', '*', '*', '*', '*', '*', '*', '*', 5, 3, 2, 2]],
        [9, 15, ['*', '*', '*', '*', '*', '*', '*', '*', '*', '*', 13, 8, 5, 3, 2, 2]],
        [16, 25, ['*', '*', '*', '*', '*', '*', '*', '*', '*', 20, 13, 8, 5, 3, 3, 2]],
        [26, 50, ['*', '*', '*', '*', '*', '*', '*', '*', 32, 20, 13, 8, 5, 5, 5, 3]],
        [51, 90, ['*', '*', '*', '*', '*', '*', 80, 50, 32, 20, 13, 8, 7, 6, 5, 4]],
        [91, 150, ['*', '*', '*', '*', '*', 125, 80, 50, 32, 20, 13, 12, 11, 7, 6, 5]],
        [151, 280, ['*', '*', '*', '*', 200, 125, 80, 50, 32, 20, 20, 19, 13, 10, 7, 6]],
        [281, 500, ['*', '*', '*', 315, 200, 125, 80, 50, 48, 47, 29, 21, 16, 11, 9, 7]],
        [501, 1200, ['*', 800, 500, 315, 200, 125, 80, 75, 73, 47, 34, 27, 19, 15, 11, 8]],
        [1201, 3200, [1250, 800, 500, 315, 200, 125, 120, 116, 73, 53, 42, 35, 23, 18, 13, 9]],
        [3201, 10000, [1250, 800, 500, 315, 200, 192, 189, 116, 86, 68, 50, 38, 29, 22, 15, 9]],
        [10001, 35000, [1250, 800, 500, 315, 300, 294, 189, 135, 108, 77, 60, 46, 35, 29, 15, 9]],
        [35001, 150000, [1250, 800, 500, 490, 476, 294, 218, 170, 123, 96, 74, 56, 40, 29, 15, 9]],
        [150001, 500000, [1250, 800, 750, 715, 476, 345, 270, 200, 156, 119, 90, 64, 40, 29, 15, 9]],
        [500001, null, [1250, 1200, 1112, 715, 556, 435, 303, 244, 189, 143, 102, 64, 40, 29, 15, 9]],
    ];

    public static function ensureLoaded(): void
    {
        $expected = count(self::AQLS) * count(self::BANDS);
        $loaded = SamplingPlan::query()->where('standard_ref', self::STANDARD)->count();

        if ($loaded < $expected) {
            $rows = self::rows();
            $existing = SamplingPlan::query()->whereIn('code', array_column($rows, 'code'))->pluck('code')->all();
            $now = now();
            $missing = [];

            foreach ($rows as $row) {
                if (in_array($row['code'], $existing, true)) {
                    continue;
                }

                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $missing[] = $row;
            }

            if ($missing !== []) {
                SamplingPlan::query()->insertOrIgnore($missing);
            }
        }

        self::retireLegacy();
    }

    public static function formatAql(float|string $aql): string
    {
        $formatted = number_format((float) $aql, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public static function makeCode(float|string $aql, int $lotMin, int $lotMax): string
    {
        return 'ML08-'.str_replace('.', '_', self::formatAql($aql)).'-'.$lotMin.'-'.$lotMax;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::BANDS as [$min, $max, $samples]) {
            $lotMax = $max ?? self::OPEN_LOT;
            $lotName = $max === null ? $min.' and over' : $min.' to '.$max;

            foreach (self::AQLS as $index => $aql) {
                $sample = $samples[$index];
                $entire = $sample === '*';

                $rows[] = [
                    'code' => self::makeCode($aql, $min, $lotMax),
                    'name' => 'AQL '.self::formatAql($aql).' · '.$lotName,
                    'standard_ref' => self::STANDARD,
                    'inspection_level' => self::LEVEL,
                    'aql' => $aql,
                    'lot_min' => $min,
                    'lot_max' => $lotMax,
                    'sample_size' => $entire ? 0 : (int) $sample,
                    'accept_number' => 0,
                    'reject_number' => 1,
                    'notes' => $entire ? 'Entire lot must be inspected.' : null,
                    'is_active' => 1,
                ];
            }
        }

        return $rows;
    }

    private static function retireLegacy(): void
    {
        $legacy = SamplingPlan::query()->where('code', 'like', 'SP-II-%')->get();

        foreach ($legacy as $old) {
            $replacement = SamplingPlan::forQuantity((float) $old->lot_min);

            if ($replacement) {
                InspectionStandard::query()
                    ->where('sampling_plan_id', $old->id)
                    ->update(['sampling_plan_id' => $replacement->id]);
                InspectionReport::query()
                    ->where('sampling_plan_id', $old->id)
                    ->update(['sampling_plan_id' => $replacement->id]);
            }

            $old->delete();
        }
    }
}
