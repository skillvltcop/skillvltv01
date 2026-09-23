<?php

use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Application\Behavior\BehaviorContractValidator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes a blueprint and retrieves the same execution through the API', function () {
    $blueprintRepository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $blueprint = (new CreateBlueprint(
        $blueprintRepository,
    ))->handle(
        canonicalName: 'assessment-rubric-lifecycle',
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

    (new FreezeBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $executeResponse = $this
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

    $executeResponse->assertSuccessful();

    $executeResponse->assertJsonStructure([
        'execution_id',
        'blueprint_id',
        'revision_id',
        'status',
        'output',
        'error',
    ]);

    $executeResponse->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $executeResponse->assertJsonPath(
        'revision_id',
        (string) $revision->id(),
    );

    $executeResponse->assertJsonPath(
        'status',
        'completed',
    );

    $executeResponse->assertJsonPath(
        'output.steps.0',
        'validate',
    );

    $executeResponse->assertJsonPath(
        'output.steps.1',
        'score',
    );

    $executeResponse->assertJsonPath(
        'error',
        null,
    );

    $executionId = $executeResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $showResponse = $this
        ->actingAs($user)
        ->getJson(
            "/api/executions/{$executionId}",
        );

    $showResponse->assertSuccessful();

    $showResponse->assertJsonStructure([
        'id',
        'blueprint_id',
        'revision_id',
        'status',
        'input',
        'context',
        'output',
        'error',
    ]);

    expect($showResponse->json('id'))
        ->toBe($executionId);

    expect($showResponse->json('blueprint_id'))
        ->toBe((string) $blueprint->id());

    expect($showResponse->json('revision_id'))
        ->toBe((string) $revision->id());

    expect($showResponse->json('status'))
        ->toBe('completed');

    expect($showResponse->json('input'))
        ->toBe([
            'student' => [
                'name' => 'Ahmed',
            ],
        ]);

    expect($showResponse->json('context'))
        ->toBe([
            'locale' => 'ar',
        ]);

    expect($showResponse->json('output'))
        ->toBe([
            'steps' => [
                'validate',
                'score',
            ],
        ]);

    expect($showResponse->json('error'))
        ->toBeNull();
});

it('forbids a user from retrieving another user blueprint execution', function () {
    $blueprintRepository = new EloquentBlueprintRepository();

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $blueprint = (new CreateBlueprint(
        $blueprintRepository,
    ))->handle(
        canonicalName: 'execution-show-owner-protected',
        namespace: 'skillvlt.edu.execution',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

$revision = (new AddBlueprintRevision(
    repository: $blueprintRepository,
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

    (new FreezeBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $executeResponse = $this
        ->actingAs($owner)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $executeResponse->assertSuccessful();

    $executionId = $executeResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $showResponse = $this
        ->actingAs($otherUser)
        ->getJson(
            "/api/executions/{$executionId}",
        );

    $showResponse
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

it('allows an authenticated user to retrieve an execution of a system-owned blueprint', function () {
    $blueprintRepository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $blueprint = (new CreateBlueprint(
        $blueprintRepository,
    ))->handle(
        canonicalName: 'system-execution-show',
        namespace: 'skillvlt.edu.system',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

$revision = (new AddBlueprintRevision(
    repository: $blueprintRepository,
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
                        'steps' => ['validate'],
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

    (new FreezeBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint(
        $blueprintRepository,
    ))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $executeResponse = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => (string) $revision->id(),
                'input' => [],
                'context' => [],
            ],
        );

    $executeResponse->assertSuccessful();

    $executionId = $executeResponse->json('execution_id');

    expect($executionId)->not->toBeNull();

    $showResponse = $this
        ->actingAs($user)
        ->getJson(
            "/api/executions/{$executionId}",
        );

    $showResponse->assertSuccessful();

    $showResponse->assertJsonPath(
        'id',
        $executionId,
    );

    $showResponse->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $showResponse->assertJsonPath(
        'revision_id',
        (string) $revision->id(),
    );

    $showResponse->assertJsonPath(
        'status',
        'completed',
    );
});