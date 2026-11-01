<?php

use Modules\Quality\Support\InspectionReadings;

it('parses a plus-minus specification into limits', function () {
    $limits = InspectionReadings::limits(null, null, '50+-2');

    expect($limits)->toBe(['min' => 48.0, 'max' => 52.0])
        ->and(InspectionReadings::state('51', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('47', $limits['min'], $limits['max']))->toBe('ng')
        ->and(InspectionReadings::state('50', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('45', $limits['min'], $limits['max']))->toBe('ng')
        ->and(InspectionReadings::state('', $limits['min'], $limits['max']))->toBe('empty');
});

it('scores length readings against a plus-minus mm specification', function () {
    $limits = InspectionReadings::limits(null, null, '55 ± 0.4 mm');

    expect($limits)->toBe(['min' => 54.6, 'max' => 55.4])
        ->and(InspectionReadings::state('55', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('54.6', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('55.4', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('60', $limits['min'], $limits['max']))->toBe('ng')
        ->and(InspectionReadings::state('50', $limits['min'], $limits['max']))->toBe('ng')
        ->and(InspectionReadings::state('20', $limits['min'], $limits['max']))->toBe('ng');
});

it('does not score free text against a range', function () {
    expect(InspectionReadings::state('GO pass', null, null))->toBe('empty')
        ->and(InspectionReadings::state('50', null, null))->toBe('empty')
        ->and(InspectionReadings::limits(null, null, 'M10 x 1.5 GO / NO-GO'))->toBe(['min' => null, 'max' => null])
        ->and(InspectionReadings::hasLimits(null, null))->toBeFalse()
        ->and(InspectionReadings::manualStatus('ng'))->toBe('ng')
        ->and(InspectionReadings::manualStatus('OK'))->toBe('ok')
        ->and(InspectionReadings::manualStatus(''))->toBe('empty');
});

it('keeps a stored tolerance ahead of the specification text', function () {
    expect(InspectionReadings::limits(11.7, 12.3, '12.0 ± 0.3 mm'))
        ->toBe(['min' => 11.7, 'max' => 12.3]);
});

it('marks the side not ok when any sample is outside the limit', function () {
    $status = InspectionReadings::status(['51', '47', '50', '45'], 48, 52);

    expect($status)->toBe('ng')
        ->and(InspectionReadings::status(['51', '50'], 48, 52))->toBe('ok')
        ->and(InspectionReadings::rollup(['ok', 'ng']))->toBe('ng');
});

it('parses one-sided minus tolerances used on diameter specs', function () {
    $limits = InspectionReadings::limits(null, null, 'ø2.9 -0.5 (6PLS)');

    expect($limits)->toBe(['min' => 2.4, 'max' => 2.9])
        ->and(InspectionReadings::state('2.9', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('2.4', $limits['min'], $limits['max']))->toBe('ok')
        ->and(InspectionReadings::state('3.1', $limits['min'], $limits['max']))->toBe('ng')
        ->and(InspectionReadings::state('2.3', $limits['min'], $limits['max']))->toBe('ng');
});

it('parses one-sided plus and asymmetric tolerances', function () {
    expect(InspectionReadings::limits(null, null, '2.9 +0.5'))
        ->toBe(['min' => 2.9, 'max' => 3.4])
        ->and(InspectionReadings::limits(null, null, '10 +0.2/-0.1'))
        ->toBe(['min' => 9.9, 'max' => 10.2])
        ->and(InspectionReadings::limits(null, null, '2.4-2.9'))
        ->toBe(['min' => 2.4, 'max' => 2.9]);
});
