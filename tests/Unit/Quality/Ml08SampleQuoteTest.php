<?php

use Modules\Quality\Support\Ml08SampleQuote;

it('looks up a different sample size for each class aql', function () {
    $quotes = Ml08SampleQuote::forClassesFromRows(1000, ['C', 'M', 'O', 'Mi']);

    expect($quotes['C']['aql'])->toBe('1')
        ->and($quotes['C']['sample_size'])->toBe(34)
        ->and(Ml08SampleQuote::rateLabel($quotes['C']['sampling_rate']))->toBe('3.4%')
        ->and($quotes['M']['aql'])->toBe('2.5')
        ->and($quotes['M']['sample_size'])->toBe(19)
        ->and(Ml08SampleQuote::rateLabel($quotes['M']['sampling_rate']))->toBe('1.9%')
        ->and($quotes['O']['sample_size'])->toBe(11)
        ->and($quotes['Mi']['sample_size'])->toBe(11)
        ->and($quotes['O']['accept_number'])->toBe(0)
        ->and($quotes['O']['reject_number'])->toBe(1);

    $lot = Ml08SampleQuote::forClassesFromRows(150, ['C', 'M', 'O']);

    expect($lot['C']['sample_size'])->toBe(13)
        ->and(Ml08SampleQuote::rateLabel($lot['C']['sampling_rate']))->toBe('8.67%')
        ->and($lot['M']['sample_size'])->toBe(11)
        ->and($lot['O']['sample_size'])->toBe(6);
});

it('inspects a star cell and a short lot in full', function () {
    $star = Ml08SampleQuote::forClassesFromRows(5, ['C']);
    $short = Ml08SampleQuote::forClassesFromRows(10, ['C']);
    $one = Ml08SampleQuote::forClassesFromRows(1, ['M']);

    expect($star['C']['sample_size'])->toBe(5)
        ->and($star['C']['entire_lot'])->toBeTrue()
        ->and($star['C']['sampling_rate'])->toBe(1.0)
        ->and($short['C']['sample_size'])->toBe(10)
        ->and($short['C']['entire_lot'])->toBeFalse()
        ->and($short['C']['sampling_rate'])->toBe(1.0)
        ->and($one['M']['sample_size'])->toBe(1)
        ->and($one['M']['sampling_rate'])->toBe(1.0);
});

it('counts one defective unit when two characteristics fail the same piece', function () {
    $samePiece = Ml08SampleQuote::judge(34, 0, [
        ['readings' => ['13', '12.0'], 'min' => 11.7, 'max' => 12.3],
        ['readings' => ['1', '60'], 'min' => 60, 'max' => 80],
    ]);
    $twoPieces = Ml08SampleQuote::judge(34, 0, [
        ['readings' => ['13', '12.0'], 'min' => 11.7, 'max' => 12.3],
        ['readings' => ['70', '1'], 'min' => 60, 'max' => 80],
    ]);
    $clean = Ml08SampleQuote::judge(34, 0, [
        ['readings' => ['', '12.0'], 'min' => 11.7, 'max' => 12.3],
    ]);

    expect($samePiece['defectives'])->toBe(1)
        ->and($samePiece['percent'])->toBe(2.94)
        ->and($samePiece['result'])->toBe('reject')
        ->and($twoPieces['defectives'])->toBe(2)
        ->and($twoPieces['result'])->toBe('reject')
        ->and($clean['defectives'])->toBe(0)
        ->and($clean['result'])->toBe('accept');
});

it('does not count an empty manual reading as a defective unit', function () {
    $placed = Ml08SampleQuote::judge(6, 0, [
        ['readings' => ['crack', ''], 'manual' => true, 'status' => 'ng'],
        ['readings' => ['chip', ''], 'manual' => true, 'status' => 'ng'],
    ]);
    $unplaced = Ml08SampleQuote::judge(6, 0, [
        ['readings' => ['', ''], 'manual' => true, 'status' => 'ng'],
    ]);

    expect($placed['defectives'])->toBe(1)
        ->and($placed['result'])->toBe('reject')
        ->and($unplaced['defectives'])->toBe(1)
        ->and($unplaced['result'])->toBe('reject');
});
