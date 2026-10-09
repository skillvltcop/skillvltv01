<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('returns a teacher-facing result without exposing execution internals', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
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

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'execution_id',
        'status',
        'result',
        'error',
    ]);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', 59);
    $response->assertJsonPath('result.max_score', 100);
    $response->assertJsonPath('result.percentage', 59);
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
                'max_score' => 100,
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', 59);
    $response->assertJsonPath('result.max_score', 100);
    $response->assertJsonPath('result.percentage', 59);
    $response->assertJsonPath('result.positioning', 'needs_support');

    $response->assertJsonMissingPath('blueprint_id');
    $response->assertJsonMissingPath('revision_id');
});

it('executes the assessment score calculator through its teacher-facing identifier', function () {
    $user = User::factory()->create();

    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $response = $this
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

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'execution_id',
        'status',
        'result',
        'error',
    ]);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', 17);
    $response->assertJsonPath('result.percentage', 85);

    $response->assertJsonMissingPath('output');
    $response->assertJsonMissingPath('revision_id');
    $response->assertJsonMissingPath('blueprint_id');
});

it('rejects unauthenticated teacher tool execution', function () {
    $response = $this->postJson(
        '/api/teacher/tools/assessment-positioning/execute',
        [
            'input' => [
                'score' => 59,
                'max_score' => 100,
            ],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});

it('rejects unauthenticated teacher blueprint execution', function () {
    $response = $this->postJson(
        '/api/teacher/tools/not-a-tool/execute',
        [
            'input' => [
                'score' => 59,
                'max_score' => 100,
            ],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});

it('forbids executing another user-owned blueprint through the teacher endpoint', function () {
    $repository = new EloquentBlueprintRepository();
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'private-teacher-assessment',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($actor)
        ->postJson(
            "/api/teacher/tools/{$blueprint->canonicalName()}/execute",
            [
                'input' => [],
                'context' => [],
            ],
        );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});
