<?php

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('returns the aggregate and per-learner fields consumed by the results analyzer page', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-results-analyzer/execute', [
            'input' => [
                'learners' => [
                    [
                        'name' => 'Amina',
                        'score' => 12,
                        'max_score' => 20,
                    ],
                    [
                        'name' => 'Youssef',
                        'score' => 5,
                        'max_score' => 20,
                    ],
                ],
            ],
            'context' => ['locale' => 'en'],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.count', 2);
    $response->assertJsonPath('result.average_percentage', 42.5);
    $response->assertJsonPath('result.ready_count', 1);
    $response->assertJsonPath('result.needs_support_count', 1);
    $response->assertJsonPath('result.success_rate', 50);
    $response->assertJsonPath('result.support_threshold', 60);
    $response->assertJsonPath('result.learners.0.name', 'Amina');
    $response->assertJsonPath('result.learners.0.score', 12);
    $response->assertJsonPath('result.learners.0.max_score', 20);
    $response->assertJsonPath('result.learners.0.percentage', 60);
    $response->assertJsonPath('result.learners.0.positioning', 'ready');
    $response->assertJsonPath('result.learners.1.name', 'Youssef');
    $response->assertJsonPath('result.learners.1.percentage', 25);
    $response->assertJsonPath('result.learners.1.positioning', 'needs_support');
});
