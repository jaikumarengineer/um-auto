<?php

namespace Modules\Quality\Support;

class InspectionReadings
{
    /**
     * @return array{min: ?float, max: ?float}
     */
    public static function limits(mixed $min, mixed $max, ?string $specification): array
    {
        if (is_numeric($min) && is_numeric($max)) {
            return ['min' => (float) $min, 'max' => (float) $max];
        }

        $spec = trim((string) $specification);

        if ($spec === '') {
            return ['min' => null, 'max' => null];
        }

        // Bilateral: 55 ± 0.4 / 50+-2 / 50 +/- 2
        if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*(?:±|\+\/-\s*|\+\-)\s*(\d+(?:\.\d+)?)/u', $spec, $matches)) {
            $nominal = (float) $matches[1];
            $tolerance = (float) $matches[2];

            return ['min' => $nominal - $tolerance, 'max' => $nominal + $tolerance];
        }

        // Asymmetric: 10 +0.2/-0.1 or 10 +0.2 / -0.1
        if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)\s*\/\s*-?\s*(\d+(?:\.\d+)?)/u', $spec, $matches)) {
            $nominal = (float) $matches[1];

            return [
                'min' => $nominal - (float) $matches[3],
                'max' => $nominal + (float) $matches[2],
            ];
        }

        // One-sided plus: 2.9 +0.5 (not part of +/-)
        if (preg_match('/(?<![\d.])(\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)(?![\d.\/])/u', $spec, $matches)) {
            $nominal = (float) $matches[1];
            $tolerance = (float) $matches[2];

            return ['min' => $nominal, 'max' => $nominal + $tolerance];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:–|—|\bto\b)\s*(\d+(?:\.\d+)?)/iu', $spec, $matches)) {
            return ['min' => (float) $matches[1], 'max' => (float) $matches[2]];
        }

        // Range "2.4-2.9" or one-sided minus "ø2.9 -0.5 (6PLS)" / "2.9-0.5"
        if (preg_match('/(?<![\d.])(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)(?![\d.])/', $spec, $matches)) {
            $first = (float) $matches[1];
            $second = (float) $matches[2];

            if ($first < $second) {
                return ['min' => $first, 'max' => $second];
            }

            // Nominal with minus tolerance only → upper = nominal, lower = nominal - tol
            return ['min' => $first - $second, 'max' => $first];
        }

        return ['min' => null, 'max' => null];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    public static function pad(array $values, int $size): array
    {
        $size = max(0, $size);
        $values = array_slice(array_values($values), 0, $size);

        while (count($values) < $size) {
            $values[] = '';
        }

        return array_map(fn ($value) => trim((string) $value), $values);
    }

    public static function hasLimits(mixed $min, mixed $max): bool
    {
        return is_numeric($min) && is_numeric($max);
    }

    public static function manualStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, ['ok', 'ng'], true) ? $status : 'empty';
    }

    public static function state(mixed $value, mixed $min, mixed $max): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return 'empty';
        }

        if (! is_numeric($min) || ! is_numeric($max)) {
            return 'empty';
        }

        if (! is_numeric($text)) {
            return 'ng';
        }

        $number = (float) $text;

        return $number + 0.0000001 >= (float) $min && $number - 0.0000001 <= (float) $max
            ? 'ok'
            : 'ng';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public static function status(array $values, mixed $min, mixed $max): string
    {
        $states = array_map(fn ($value) => self::state($value, $min, $max), $values);

        if (in_array('ng', $states, true)) {
            return 'ng';
        }

        if (in_array('ok', $states, true)) {
            return 'ok';
        }

        return 'empty';
    }

    /**
     * @param  list<string>  $statuses
     */
    public static function rollup(array $statuses): string
    {
        if (in_array('ng', $statuses, true)) {
            return 'ng';
        }

        if (in_array('ok', $statuses, true)) {
            return 'ok';
        }

        return 'empty';
    }

    /**
     * @param  array<int, mixed>  $supplier
     * @param  array<int, mixed>  $umac
     */
    public static function summary(array $supplier, array $umac, mixed $min, mixed $max): ?string
    {
        foreach ([$umac, $supplier] as $side) {
            foreach ($side as $value) {
                if (self::state($value, $min, $max) === 'ng') {
                    return mb_substr(trim((string) $value), 0, 255);
                }
            }
        }

        foreach ([...$umac, ...$supplier] as $value) {
            $text = trim((string) $value);

            if ($text !== '') {
                return mb_substr($text, 0, 255);
            }
        }

        return null;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'ok' => __('OK'),
            'ng' => __('Not OK'),
            default => '—',
        };
    }
}
