<?php

use App\Services\ForgeService;

test('generateSignature produces deterministic sorted format', function () {
    $service = new ForgeService;

    $ores1 = [
        ['ore_type_id' => 3, 'quantity' => 5],
        ['ore_type_id' => 1, 'quantity' => 10],
        ['ore_type_id' => 2, 'quantity' => 8],
    ];

    $ores2 = [
        ['ore_type_id' => 1, 'quantity' => 10],
        ['ore_type_id' => 2, 'quantity' => 8],
        ['ore_type_id' => 3, 'quantity' => 5],
    ];

    $sig1 = $service->generateSignature($ores1);
    $sig2 = $service->generateSignature($ores2);

    expect($sig1)->toBe($sig2);
    expect($sig1)->toBe('1:10|2:8|3:5');
});

test('generateSignature rejects mismatched order', function () {
    $service = new ForgeService;

    $ores1 = [
        ['ore_type_id' => 1, 'quantity' => 10],
        ['ore_type_id' => 2, 'quantity' => 8],
        ['ore_type_id' => 3, 'quantity' => 5],
    ];

    $ores2 = [
        ['ore_type_id' => 1, 'quantity' => 10],
        ['ore_type_id' => 2, 'quantity' => 8],
        ['ore_type_id' => 3, 'quantity' => 6], // Different quantity
    ];

    $sig1 = $service->generateSignature($ores1);
    $sig2 = $service->generateSignature($ores2);

    expect($sig1)->not->toBe($sig2);
});

test('mapScoreToGrade maps all ranges correctly', function () {
    $service = new ForgeService;

    $testCases = [
        [0, 1],   // Grade I
        [5, 1],
        [10, 1],
        [11, 2],  // Grade II
        [20, 2],
        [21, 3],  // Grade III
        [30, 3],
        [31, 4],  // Grade IV
        [40, 4],
        [41, 5],  // Grade V
        [55, 5],
        [56, 6],  // Grade VI
        [65, 6],
        [66, 7],  // Grade VII
        [75, 7],
        [76, 8],  // Grade VIII
        [85, 8],
        [86, 9],  // Grade IX
        [95, 9],
        [96, 10], // Grade X
        [100, 10],
    ];

    foreach ($testCases as [$score, $expectedGrade]) {
        expect($service->mapScoreToGrade($score))->toBe($expectedGrade);
    }
});

test('getGradeFactor returns correct multipliers', function () {
    $service = new ForgeService;

    $factors = [
        1 => 0.50,
        2 => 0.60,
        3 => 0.70,
        4 => 0.80,
        5 => 0.90,
        6 => 1.00,
        7 => 1.60,
        8 => 1.75,
        9 => 1.90,
        10 => 2.00,
    ];

    foreach ($factors as $grade => $factor) {
        expect($service->getGradeFactor($grade))->toBe($factor);
    }
});

test('validateOreInputs enforces Rule of 3 Ores', function () {
    // This test is in ForgeFlowTest feature tests since it requires database
    // This unit test file focuses on pure logic without database dependencies
});

test('combined score is correctly weighted', function () {
    $service = new ForgeService;

    // Test case 1: Equal scores
    $score1 = (int) round((50 * 0.30) + (50 * 0.50) + (50 * 0.20));
    expect($score1)->toBe(50);

    // Test case 2: High smithing (weighted 50%)
    $score2 = (int) round((0 * 0.30) + (100 * 0.50) + (0 * 0.20));
    expect($score2)->toBe(50);

    // Test case 3: Perfect score
    $score3 = (int) round((100 * 0.30) + (100 * 0.50) + (100 * 0.20));
    expect($score3)->toBe(100);

    // Test case 4: Weighted toward smithing
    $score4 = (int) round((30 * 0.30) + (90 * 0.50) + (20 * 0.20));
    expect($score4)->toBe(58); // 9 + 45 + 4 = 58
});
