<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Domain\Execution\ValueObjects\ExecutionId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('retrieves a completed execution through the HTTP API', function () {
    $blueprintRepository = new EloquentBlueprintRepository();
    $user = User::factory()->create();

    $blueprint = (new CreateBlueprint($blueprintRepository))->handle(
        canonicalName: 'assessment-rubric-read',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    (new FreezeBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $blueprint = $blueprintRepository->find($blueprint->id());

    expect($blueprint)->not->toBeNull();

    $engine = new ExecutionEngine(
        runner: new BehaviorRunner(),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $executionRepository = new EloquentExecutionRepository();

    $executionRepository->save($execution);

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/executions/{$execution->id()}",
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'id',
        'blueprint_id',
        'revision_id',
        'status',
        'input',
        'context',
        'output',
        'error',
    ]);

    $response->assertJsonPath(
        'id',
        (string) $execution->id(),
    );

    $response->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'revision_id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'status',
        'completed',
    );

    $response->assertJsonPath(
        'input.student.name',
        'Ahmed',
    );

    $response->assertJsonPath(
        'context.locale',
        'ar',
    );

    $response->assertJsonPath(
        'output.steps.0',
        'validate',
    );

    $response->assertJsonPath(
        'output.steps.1',
        'score',
    );

    $response->assertJsonPath(
        'error',
        null,
    );
});

it('returns 404 when the execution does not exist', function () {
    $executionId = ExecutionId::generate();
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/executions/{$executionId}",
        );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Execution not found.',
    ]);
});

it('forbids a user from reading another user execution', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $blueprintRepository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($blueprintRepository))->handle(
        canonicalName: 'execution-read-owner-protected',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: [
            'input' => [
                'type' => 'object',
            ],
        ],
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    (new FreezeBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($blueprintRepository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $blueprint = $blueprintRepository->find($blueprint->id());

    expect($blueprint)->not->toBeNull();

    $engine = new ExecutionEngine(
        runner: new BehaviorRunner(),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    $executionRepository = new EloquentExecutionRepository();

    $executionRepository->save($execution);

    $response = $this
        ->actingAs($otherUser)
        ->getJson(
            "/api/executions/{$execution->id()}",
        );

    $response->assertForbidden();
});

it('rejects unauthenticated execution access', function () {
    $executionId = ExecutionId::generate();

    $response = $this->getJson(
        "/api/executions/{$executionId}",
    );

    $response->assertUnauthorized();
});