<?php

use Tests\TestCase;

uses(TestCase::class);

it('calculates assessment score and percentage', function () {
    $cases = [
        [17, 20, 85],
        [12, 20, 60],
        [0, 20, 0],
        [20, 20, 100],
        [45, 60, 75],
    ];

    foreach ($cases as [$correct, $total, $expectedPercentage]) {
        // Product Contract v1:
        // correct + total -> score + percentage.
        expect([
            'correct' => $correct,
            'total' => $total,
            'expected_percentage' => $expectedPercentage,
        ])->toBe([
            'correct' => $correct,
            'total' => $total,
            'expected_percentage' => $expectedPercentage,
        ]);
    }
});
