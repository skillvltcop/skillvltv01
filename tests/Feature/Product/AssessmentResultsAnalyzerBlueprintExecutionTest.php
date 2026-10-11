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
