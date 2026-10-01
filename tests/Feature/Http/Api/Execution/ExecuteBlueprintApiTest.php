<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\DeprecateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Application\Behavior\BehaviorContractValidator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes a blueprint through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'execution_id',
        'blueprint_id',
        'revision_id',
        'status',
        'input',
        'context',
        'output',
    ]);

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
});

it('returns 404 when the blueprint does not exist', function () {
    $user = User::factory()->create();

    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprintId}/execute",
            [
                'revision_id' => (string) $revisionId,
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when revision_id is missing', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute-validation',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'revision_id',
    ]);
});

it('persists the execution when executed through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-execute-persistence',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertSuccessful();

    $executionId = $response->json('execution_id');

    expect($executionId)->not->toBeNull();

    $this->assertDatabaseHas('executions', [
        'id' => $executionId,
        'blueprint_id' => (string) $blueprint->id(),
        'revision_id' => (string) $revision->id(),
        'status' => 'completed',
    ]);
});

it('forbids a user from executing another user blueprint', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-owner-protected',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertForbidden();
});

it('rejects unauthenticated blueprint execution', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/execute",
        [
            'revision_id' => (string) $revisionId,
            'input' => [],
            'context' => [],
        ],
    );

    $response->assertUnauthorized();
});

it('returns a failed execution when blueprint execution fails', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-failure',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $execution = Execution::create(
        blueprintId: $blueprint->id(),
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

    $execution->start();

    $execution->fail(
        'Behavior execution failed.',
    );

    $engine = Mockery::mock(
        \App\Application\Execution\Engine\ExecutionEngineContract::class,
    );

    $engine
        ->shouldReceive('execute')
        ->once()
        ->andReturn($execution);

    $this->app->instance(
        \App\Application\Execution\Engine\ExecutionEngineContract::class,
        $engine,
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [
                    'student' => [
                        'name' => 'Ahmed',
                    ],
                ],
                'context' => [
                    'locale' => 'ar',
                ],
            ],
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'execution_id',
        'blueprint_id',
        'revision_id',
        'status',
        'input',
        'context',
        'output',
        'error',
    ]);

    $response->assertJsonPath(
        'execution_id',
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
        'failed',
    );

    $response->assertJsonPath(
        'output',
        null,
    );

    $response->assertJsonPath(
        'error',
        'Behavior execution failed.',
    );
});

it('allows a user to execute an active system-owned blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-execution-public',
        namespace: 'skillvlt.edu.system',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertSuccessful();

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
});

it('returns 422 when executing a deprecated blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-deprecated',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    (new DeprecateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Only an active Blueprint can be executed.',
    ]);
});

it('returns 422 when executing a sunset blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-sunset',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:1111111111111111111111111111111111111111111111111111111111111111',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $blueprint = $repository->find(
        new BlueprintId((string) $blueprint->id()),
    );

    $blueprint->sunset();

    $repository->save($blueprint);

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Only an active Blueprint can be executed.',
    ]);
});

it('returns 422 when executing a revision that does not belong to the blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprintA = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-blueprint-a',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revisionA = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprintA->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:2222222222222222222222222222222222222222222222222222222222222222',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprintA->id(),
        revisionId: (string) $revisionA->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprintA->id(),
        revisionId: (string) $revisionA->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprintA->id(),
    );

    $blueprintB = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-blueprint-b',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revisionB = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprintB->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:3333333333333333333333333333333333333333333333333333333333333333',
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
                        'result' => 'ok',
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

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprintA->id()}/execute",
            [
                'revision_id' => (string) $revisionB->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Revision does not belong to the Blueprint.',
    ]);
});

it('returns 422 when executing a non-current revision', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-non-current',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revisionOne = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:4444444444444444444444444444444444444444444444444444444444444444',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revisionOne->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revisionOne->id(),
    );

    $revisionTwo = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '2.0.0',
        behaviorDigest:
            'sha256:5555555555555555555555555555555555555555555555555555555555555555',
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revisionTwo->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revisionTwo->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Only the current revision can be executed.',
    ]);
});

it('returns 422 when executing an unfrozen revision', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-unfrozen',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
    repository: $repository,
    behaviorContractValidator: new BehaviorContractValidator(),
))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:6666666666666666666666666666666666666666666666666666666666666666',
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
                        'result' => 'ok',
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

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Only a frozen revision can be executed.',
    ]);
});

it('returns 422 when input or context is null', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'execution-null-input-context',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest:
            'sha256:' . str_repeat('a', 64),
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
                        'result' => 'ok',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => null,
                'context' => null,
            ],
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'input',
        'context',
    ]);
});