<?php

namespace Modules\Quality\Support;

use Modules\Quality\Models\InspectionCharacteristic;
use Modules\Quality\Models\InspectionReportSample;
use Modules\Quality\Models\SamplingPlan;

/**
 * Sample size for one lot, split by characteristic class.
 * n comes from the ML-08 band for that class AQL. The rate is n / lot size.
 */
class Ml08SampleQuote
{
    /** @var array<string, string> */
    public const CLASS_AQL = [
        'C' => '1',
        'M' => '2.5',
        'Mi' => '6.5',
        'O' => '6.5',
    ];

    /** @var list<string> */
    public const CLASS_ORDER = ['C', 'M', 'Mi', 'O'];

    /**
     * @param  list<string>  $classes
     * @return array<string, array<string, mixed>>
     */
    public static function forClasses(float $qty, array $classes): array
    {
        Ml08SamplingPlan::ensureLoaded();

        $lotSize = (int) ceil($qty);
        $quotes = [];

        foreach (self::ordered($classes) as $class) {
            $aql = self::CLASS_AQL[$class] ?? self::CLASS_AQL['O'];
            $plan = $lotSize >= 2 ? SamplingPlan::forQuantity($lotSize, $aql) : null;
            $row = $plan ? [
                'aql' => $plan->aql,
                'lot_min' => $plan->lot_min,
                'lot_max' => $plan->lot_max,
                'sample_size' => $plan->sample_size,
                'accept_number' => $plan->accept_number,
                'reject_number' => $plan->reject_number,
                'id' => $plan->id,
            ] : self::matchRow($lotSize, $aql, Ml08SamplingPlan::rows());

            $quotes[$class] = self::quoteMatched($lotSize, $class, $aql, $row);
        }

        return $quotes;
    }

    /**
     * Same lookup against the built-in ML-08 matrix, with no database read.
     *
     * @param  list<string>  $classes
     * @return array<string, array<string, mixed>>
     */
    public static function forClassesFromRows(float $qty, array $classes): array
    {
        $lotSize = (int) ceil($qty);
        $rows = Ml08SamplingPlan::rows();
        $quotes = [];

        foreach (self::ordered($classes) as $class) {
            $aql = self::CLASS_AQL[$class] ?? self::CLASS_AQL['O'];
            $quotes[$class] = self::quoteMatched($lotSize, $class, $aql, self::matchRow($lotSize, $aql, $rows));
        }

        return $quotes;
    }

    /**
     * @param  array<string, array<string, mixed>>  $quotes
     * @param  iterable<int, InspectionReportSample>  $samples
     * @return array<string, array<string, mixed>>
     */
    public static function keepSnapshot(array $quotes, iterable $samples): array
    {
        $saved = [];

        foreach ($samples as $sample) {
            $saved[$sample->characteristic_class] = $sample;
        }

        foreach ($quotes as $class => $quote) {
            if (! isset($saved[$class])) {
                continue;
            }

            $row = $saved[$class];
            $quotes[$class]['aql'] = Ml08SamplingPlan::formatAql($row->aql);
            $quotes[$class]['lot_min'] = (int) $row->lot_min;
            $quotes[$class]['lot_max'] = (int) $row->lot_max;
            $quotes[$class]['sample_size'] = (int) $row->sample_size;
            $quotes[$class]['sampling_rate'] = (float) $row->sampling_rate;
            $quotes[$class]['accept_number'] = (int) $row->accept_number;
            $quotes[$class]['reject_number'] = (int) $row->reject_number;
            $quotes[$class]['sampling_plan_id'] = $row->sampling_plan_id;
            $quotes[$class]['entire_lot'] = false;
        }

        return $quotes;
    }

    /**
     * @param  iterable<int, InspectionReportSample>  $samples
     * @return array<string, array<string, mixed>>
     */
    public static function fromSnapshot(iterable $samples): array
    {
        $quotes = [];

        foreach ($samples as $row) {
            $class = InspectionCharacteristic::normalize($row->characteristic_class);
            $quotes[$class] = [
                'class' => $class,
                'aql' => Ml08SamplingPlan::formatAql($row->aql),
                'lot_min' => (int) $row->lot_min,
                'lot_max' => (int) $row->lot_max,
                'sample_size' => (int) $row->sample_size,
                'sampling_rate' => (float) $row->sampling_rate,
                'accept_number' => (int) $row->accept_number,
                'reject_number' => (int) $row->reject_number,
                'sampling_plan_id' => $row->sampling_plan_id,
                'entire_lot' => false,
            ];
        }

        $ordered = [];

        foreach (self::CLASS_ORDER as $class) {
            if (isset($quotes[$class])) {
                $ordered[$class] = $quotes[$class];
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, array<string, mixed>>  $quotes
     * @return array<string, mixed>|null
     */
    public static function headline(array $quotes): ?array
    {
        $best = null;

        foreach (self::CLASS_ORDER as $class) {
            if (! isset($quotes[$class])) {
                continue;
            }

            if ($best === null || (int) $quotes[$class]['sample_size'] > (int) $best['sample_size']) {
                $best = $quotes[$class];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, array<string, mixed>>  $quotes
     */
    public static function summary(array $quotes): string
    {
        $parts = [];

        foreach (self::CLASS_ORDER as $class) {
            if (! isset($quotes[$class])) {
                continue;
            }

            $quote = $quotes[$class];
            $label = InspectionCharacteristic::CLASSES[$class] ?? $class;
            $parts[] = $label.' AQL '.$quote['aql'].' · '.$quote['sample_size'].' · '.self::rateLabel((float) $quote['sampling_rate']);
        }

        return implode(' · ', $parts);
    }

    public static function rateLabel(float $rate): string
    {
        return self::trimNumber($rate * 100).'%';
    }

    public static function percentLabel(float $percent): string
    {
        return self::trimNumber($percent).'%';
    }

    /**
     * Count defective units in one class. One piece that fails two characteristics counts once.
     * Empty readings are not defectives. Accept number 0 rejects the class on any defective unit.
     *
     * @param  list<array{readings?: list<mixed>, manual?: bool, status?: string, min?: mixed, max?: mixed}>  $lines
     * @return array{defectives: int, percent: float, result: string}
     */
    public static function judge(int $sampleSize, int $acceptNumber, array $lines): array
    {
        $sampleSize = max(1, $sampleSize);
        $defectives = 0;

        for ($index = 0; $index < $sampleSize; $index++) {
            foreach ($lines as $line) {
                if (! self::indexIsDefective($line, $index)) {
                    continue;
                }

                $defectives++;
                break;
            }
        }

        if ($defectives === 0 && self::hasUnplacedManualReject($lines)) {
            $defectives = 1;
        }

        return [
            'defectives' => $defectives,
            'percent' => round($defectives / $sampleSize * 100, 2),
            'result' => $defectives > $acceptNumber ? 'reject' : 'accept',
        ];
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    private static function ordered(array $classes): array
    {
        $wanted = [];

        foreach ($classes as $class) {
            $wanted[InspectionCharacteristic::normalize($class)] = true;
        }

        $ordered = [];

        foreach (self::CLASS_ORDER as $class) {
            if (isset($wanted[$class])) {
                $ordered[] = $class;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function matchRow(int $lotSize, string $aql, array $rows): ?array
    {
        if ($lotSize < 2) {
            return null;
        }

        $matched = null;

        foreach ($rows as $row) {
            if (Ml08SamplingPlan::formatAql($row['aql']) !== Ml08SamplingPlan::formatAql($aql)) {
                continue;
            }

            if ((int) $row['lot_min'] <= $lotSize && (int) $row['lot_max'] >= $lotSize) {
                $matched = $row;
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>
     */
    private static function quoteMatched(int $lotSize, string $class, string $aql, ?array $row): array
    {
        $aqlLabel = Ml08SamplingPlan::formatAql($aql);

        if ($lotSize < 2 || $row === null) {
            $n = max(1, $lotSize);

            return [
                'class' => $class,
                'aql' => $aqlLabel,
                'lot_min' => 1,
                'lot_max' => $n,
                'sample_size' => $n,
                'sampling_rate' => 1.0,
                'accept_number' => 0,
                'reject_number' => 1,
                'sampling_plan_id' => $row['id'] ?? null,
                'entire_lot' => true,
            ];
        }

        $entire = (int) $row['sample_size'] === 0;
        $table = $entire ? $lotSize : (int) $row['sample_size'];
        $n = min($lotSize, max(1, $table));

        return [
            'class' => $class,
            'aql' => $aqlLabel,
            'lot_min' => (int) $row['lot_min'],
            'lot_max' => (int) $row['lot_max'],
            'sample_size' => $n,
            'sampling_rate' => round($n / $lotSize, 6),
            'accept_number' => (int) ($row['accept_number'] ?? 0),
            'reject_number' => (int) ($row['reject_number'] ?? 1),
            'sampling_plan_id' => $row['id'] ?? null,
            'entire_lot' => $entire,
        ];
    }

    /**
     * @param  array{readings?: list<mixed>, manual?: bool, status?: string, min?: mixed, max?: mixed}  $line
     */
    private static function indexIsDefective(array $line, int $index): bool
    {
        $readings = array_values($line['readings'] ?? []);
        $value = $readings[$index] ?? '';

        if (! empty($line['manual'])) {
            return InspectionReadings::manualStatus($line['status'] ?? '') === 'ng'
                && trim((string) $value) !== '';
        }

        return InspectionReadings::state($value, $line['min'] ?? null, $line['max'] ?? null) === 'ng';
    }

    /**
     * @param  list<array{readings?: list<mixed>, manual?: bool, status?: string}>  $lines
     */
    private static function hasUnplacedManualReject(array $lines): bool
    {
        foreach ($lines as $line) {
            if (empty($line['manual'])) {
                continue;
            }

            if (InspectionReadings::manualStatus($line['status'] ?? '') !== 'ng') {
                continue;
            }

            foreach ($line['readings'] ?? [] as $value) {
                if (trim((string) $value) !== '') {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private static function trimNumber(float $number): string
    {
        $text = number_format(round($number, 2), 2, '.', '');

        return rtrim(rtrim($text, '0'), '.');
    }
}
