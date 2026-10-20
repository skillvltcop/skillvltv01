<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('analyzes a complete learner group through the teacher API', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
            'input' => [
                'learners' => [
                    ['name' => 'أحمد', 'score' => 14, 'max_score' => 20],
                    ['name' => 'سارة', 'score' => 18, 'max_score' => 20],
                    ['name' => 'يوسف', 'score' => 9, 'max_score' => 20],
                    ['name' => 'مريم', 'score' => 12, 'max_score' => 20],
                ],
            ],
            'context' => ['locale' => 'ar'],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.count', 4);
    $response->assertJsonPath('result.average_percentage', 66.25);
    $response->assertJsonPath('result.ready_count', 3);
    $response->assertJsonPath('result.needs_support_count', 1);
    $response->assertJsonPath('result.success_rate', 75);
    $response->assertJsonPath('result.support_threshold', 60);
    $response->assertJsonPath('result.learners.2.name', 'يوسف');
    $response->assertJsonPath('result.learners.2.positioning', 'needs_support');
    $response->assertJsonPath('result.learners.3.positioning', 'ready');
});


it('rejects an empty learner group through the teacher API', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
            'input' => ['learners' => []],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['input.learners']);
});

it('rejects learner scores above the maximum through the teacher API', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
            'input' => [
                'learners' => [
                    ['name' => 'Learner', 'score' => 21, 'max_score' => 20],
                ],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['input.learners.0.score']);
});

it('rejects non-positive maximum scores through the teacher API', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
            'input' => [
                'learners' => [
                    ['name' => 'Learner', 'score' => 0, 'max_score' => 0],
                ],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['input.learners.0.max_score']);
});


it('rejects non-finite analyzer scores and maximum scores through the teacher API', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    foreach ([
        [
            'learner' => ['name' => 'Infinite Score', 'score' => '1e309', 'max_score' => '1e309'],
            'error' => 'input.learners.0.score',
        ],
        [
            'learner' => ['name' => 'Infinite Maximum', 'score' => 0, 'max_score' => '1e309'],
            'error' => 'input.learners.0.max_score',
        ],
    ] as $case) {
        $response = $this
            ->actingAs($user)
            ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
                'input' => ['learners' => [$case['learner']]],
                'context' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$case['error']]);
    }
});
