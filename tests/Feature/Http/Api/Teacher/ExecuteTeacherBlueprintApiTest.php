<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes a teacher tool by slug without requiring a revision id', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-positioning/execute', [
            'input' => [
                'score' => 59,
                'max_score' => 100,
            ],
            'context' => [],
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'execution_id',
        'status',
        'result',
        'error',
    ]);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', '59');
    $response->assertJsonPath('result.max_score', '100');
    $response->assertJsonPath('result.percentage', '59');
    $response->assertJsonPath('result.positioning', 'needs_support');
    $response->assertJsonMissingPath('output');
    $response->assertJsonMissingPath('revision_id');
});

it('returns 404 for an unknown teacher tool slug', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/unknown-tool/execute', [
            'input' => [
                'score' => 59,
                'max_score' => 100,
            ],
        ]);

    $response->assertNotFound();
    $response->assertJsonPath('message', 'Blueprint not found.');
});

it('keeps the teacher result contract for a ready result', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-positioning/execute', [
            'input' => [
                'score' => 60,
                'max_score' => 100,
            ],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', '60');
    $response->assertJsonPath('result.max_score', '100');
    $response->assertJsonPath('result.percentage', '60');
    $response->assertJsonPath('result.positioning', 'ready');
    $response->assertJsonPath('error', null);
});
