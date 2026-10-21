<?php

use App\Models\User;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('rejects invalid inputs before executing the assessment score calculator', function () {
    $user = User::factory()->create();

    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $invalidCases = [
        [
            'input' => ['total' => 10],
            'error' => 'input.correct',
        ],
        [
            'input' => ['correct' => 5, 'total' => 0],
            'error' => 'input.total',
        ],
        [
            'input' => ['correct' => 11, 'total' => 10],
            'error' => 'input.correct',
        ],
    ];

    foreach ($invalidCases as $case) {
        $response = $this
            ->actingAs($user)
            ->postJson('/api/teacher/tools/assessment-score-calculator/execute', [
                'input' => $case['input'],
                'context' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$case['error']]);
    }
});
