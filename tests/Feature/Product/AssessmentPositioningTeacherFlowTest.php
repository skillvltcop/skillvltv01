<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('completes the teacher use flow from discovery to execution result', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $discoveryResponse = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/teacher/blueprints/discover');

    $discoveryResponse->assertSuccessful();

    $tool = $discoveryResponse->json('data.0');

    expect($tool)->not->toBeNull();
    expect($tool['slug'])->toBe('assessment-positioning');
    expect($tool['version'])->toBe('v1.1.0');

    $executionResponse = $this
        ->actingAs($user)
        ->postJson(
            '/api/teacher/tools/assessment-positioning/execute',
            [
                'input' => [
                    'score' => 59,
                    'max_score' => 100,
                ],
                'context' => [],
            ],
        );

    $executionResponse->assertSuccessful();
    $executionResponse->assertJsonPath('status', 'completed');
    $executionResponse->assertJsonPath(
        'result.positioning',
        'needs_support',
    );

    $executionId = $executionResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $resultResponse = $this
        ->actingAs($user)
        ->getJson("/api/executions/{$executionId}");

    $resultResponse->assertSuccessful();
    $resultResponse->assertJsonPath('status', 'completed');
    $resultResponse->assertJsonPath('output.score', 59);
    $resultResponse->assertJsonPath('output.max_score', 100);
    $resultResponse->assertJsonPath('output.percentage', 59);
    $resultResponse->assertJsonPath(
        'output.positioning',
        'needs_support',
    );
});
