<?php

declare(strict_types=1);

use IlluminateFoundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('defines the assessment results analyzer v1 contract', function () {
    $input = [
        'learners' => [
            ['name' => 'أحمد', 'score' => 14, 'max_score' => 20],
            ['name' => 'سارة', 'score' => 18, 'max_score' => 20],
            ['name' => 'يوسف', 'score' => 9, 'max_score' => 20],
            ['name' => 'مريم', 'score' => 12, 'max_score' => 20],
        ],
    ];

    $expected = [
        'count' => 4,
        'average_percentage' => 66.25,
        'ready_count' => 2,
        'needs_support_count' => 2,
        'success_rate' => 50,
    ];

    expect($input['learners'])
        ->toHaveCount(4);

    expect($expected)
        ->toBe([
            'count' => 4,
            'average_percentage' => 66.25,
            'ready_count' => 2,
            'needs_support_count' => 2,
            'success_rate' => 50,
        ]);
});
