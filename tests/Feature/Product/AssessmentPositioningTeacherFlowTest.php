<?php

use AppModelsUser;
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
        ->getJson('/api/blueprints/discover');

    $discoveryResponse->assertSuccessful();

    $blueprint = $discoveryResponse->json('data.0');

    expect($blueprint)->not->toBeNull();
    expect($blueprint['canonical_name'])->toBe('assessment-positioning');
    expect($blueprint['lifecycle_status'])->toBe('active');
    expect($blueprint['current_revision_id'])->not->toBeNull();

    $executionResponse = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint['id']}/execute",
            [
                'revision_id' => $blueprint['current_revision_id'],
                'input' => [
                    'score' => 59,
                ],
                'context' => [],
            ],
        );

    $executionResponse->assertSuccessful();
    $executionResponse->assertJsonPath('status', 'completed');
    $executionResponse->assertJsonPath(
        'output.positioning',
        'needs_support',
    );

    $executionId = $executionResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $resultResponse = $this
        ->actingAs($user)
        ->getJson("/api/executions/{$executionId}");

    $resultResponse->assertSuccessful();
    $resultResponse->assertJsonPath('status', 'completed');
    $resultResponse->assertJsonPath('output.score', '59');
    $resultResponse->assertJsonPath(
        'output.positioning',
        'needs_support',
    );
});
