<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('returns a teacher-facing result without exposing execution internals', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $blueprints = (new \App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository())->discover();

    expect($blueprints)->toHaveCount(1);

    $blueprintId = (string) $blueprints[0]->id();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/teacher/blueprints/{$blueprintId}/execute",
            [
                'input' => [
                    'score' => 59,
                ],
                'context' => [],
            ],
        );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'execution_id',
        'status',
        'result',
        'error',
    ]);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', '59');
    $response->assertJsonPath('result.positioning', 'needs_support');

    $response->assertJsonMissingPath('output');
    $response->assertJsonMissingPath('revision_id');
    $response->assertJsonMissingPath('blueprint_id');
});

it('executes the assessment positioning tool through its teacher-facing identifier', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $this->actingAs($user);

    $response = $this->postJson(
        '/api/teacher/tools/assessment-positioning/execute',
        [
            'input' => [
                'score' => 59,
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', '59');
    $response->assertJsonPath('result.positioning', 'needs_support');

    $response->assertJsonMissingPath('blueprint_id');
    $response->assertJsonMissingPath('revision_id');
});

it('rejects unauthenticated teacher tool execution', function () {
    $response = $this->postJson(
        '/api/teacher/tools/assessment-positioning/execute',
        [
            'input' => [
                'score' => 59,
            ],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});

it('rejects unauthenticated teacher blueprint execution', function () {
    $response = $this->postJson(
        '/api/teacher/blueprints/not-a-blueprint/execute',
        [
            'input' => [
                'score' => 59,
            ],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});
