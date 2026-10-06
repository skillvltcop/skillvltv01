<?php

use App\Models\User;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('completes the teacher use flow from discovery to execution result', function () {
    $user = User::factory()->create();

    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $discoveryResponse = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/teacher/blueprints/discover');

    $discoveryResponse->assertSuccessful();

    $tool = $discoveryResponse->json('data.0');

    expect($tool)->not->toBeNull();
    expect($tool['slug'])->toBe('assessment-score-calculator');
    expect($tool['version'])->toBe('v1.0.0');

    $executionResponse = $this
        ->actingAs($user)
        ->postJson(
            '/api/teacher/tools/assessment-score-calculator/execute',
            [
                'input' => [
                    'correct' => 17,
                    'total' => 20,
                ],
                'context' => [],
            ],
        );

    $executionResponse->assertSuccessful();
    $executionResponse->assertJsonPath('status', 'completed');
    $executionResponse->assertJsonPath('result.score', '17');
    $executionResponse->assertJsonPath('result.percentage', '85');

    $executionId = $executionResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $resultResponse = $this
        ->actingAs($user)
        ->getJson("/api/executions/{$executionId}");

    $resultResponse->assertSuccessful();
    $resultResponse->assertJsonPath('status', 'completed');
    $resultResponse->assertJsonPath('output.score', '17');
    $resultResponse->assertJsonPath('output.percentage', '85');
});
