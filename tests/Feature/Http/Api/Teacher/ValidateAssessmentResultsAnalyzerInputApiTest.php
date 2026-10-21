<?php

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('rejects invalid learner data before executing the assessment results analyzer', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $invalidCases = [
        [
            'input' => ['learners' => []],
            'error' => 'input.learners',
        ],
        [
            'input' => [
                'learners' => [
                    ['score' => 5, 'max_score' => 10],
                ],
            ],
            'error' => 'input.learners.0.name',
        ],
        [
            'input' => [
                'learners' => [
                    ['name' => 'Amina', 'score' => -1, 'max_score' => 10],
                ],
            ],
            'error' => 'input.learners.0.score',
        ],
        [
            'input' => [
                'learners' => [
                    ['name' => 'Amina', 'score' => 11, 'max_score' => 10],
                ],
            ],
            'error' => 'input.learners.0.score',
        ],
    ];

    foreach ($invalidCases as $case) {
        $response = $this
            ->actingAs($user)
            ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
                'input' => $case['input'],
                'context' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$case['error']]);
    }
});
