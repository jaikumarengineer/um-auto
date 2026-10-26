<?php

namespace Modules\Quality\Support;

class Qty
{
    public static function num(mixed $value): float
    {
        return round((float) $value, 3);
    }

    public static function format(mixed $value): string
    {
        $formatted = number_format(self::num($value), 3, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public static function money(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', ',');
    }

    public static function exceeds(mixed $value, mixed $limit): bool
    {
        return self::num($value) - self::num($limit) > 0.0005;
    }

    public static function same(mixed $left, mixed $right): bool
    {
        return abs(self::num($left) - self::num($right)) <= 0.0005;
    }
}
