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
use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
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

    $revision = (new AddBlueprintRevision(
    repository: $blueprintRepository,
    behaviorContractValidator: new \App\Application\Behavior\BehaviorContractValidator(),
))->handle(
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
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'steps' => ['validate', 'score'],
                    ],
                ],
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
       runner: new BehaviorRunner(
            new ValueResolver(),
        ),
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        ownerId: (string) $user->id,
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

    $revision = (new AddBlueprintRevision(
    repository: $blueprintRepository,
    behaviorContractValidator: new \App\Application\Behavior\BehaviorContractValidator(),
))->handle(
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
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'steps' => ['validate', 'score'],
                    ],
                ],
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
        $runner = new BehaviorRunner(
            new ValueResolver(),
        )
    );

    $execution = $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        ownerId: (string) $owner->id,
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

it('only lets the execution owner read a system blueprint execution', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $blueprint = \App\Models\Blueprint::query()->create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'canonical_name' => 'system-execution-owner-protected',
        'namespace' => 'skillvlt.edu.execution',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);

    $revision = \App\Models\BlueprintRevision::query()->create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'blueprint_id' => (string) $blueprint->id,
        'revision_number' => '1.0.0',
        'parent_revision_id' => null,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);

    $execution = \App\Domain\Execution\Entities\Execution::create(
        blueprintId: new \App\Domain\Blueprint\ValueObjects\BlueprintId(
            (string) $blueprint->id,
        ),
        revisionId: new \App\Domain\Blueprint\ValueObjects\RevisionId(
            (string) $revision->id,
        ),
        input: ['secret' => 'owner-only'],
        context: [],
        ownerId: (string) $owner->id,
    );

    (new \App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository())
        ->save($execution);

    $this
        ->actingAs($owner)
        ->getJson("/api/executions/{$execution->id()}")
        ->assertSuccessful();

    $this
        ->actingAs($otherUser)
        ->getJson("/api/executions/{$execution->id()}")
        ->assertForbidden();
});
