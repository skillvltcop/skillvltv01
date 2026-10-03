<?php

use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('adds a revision to a blueprint through the HTTP API', function () {

$user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-revision',
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
        "/api/blueprints/{$blueprint->id()}/revisions",
        [
            'number' => '1.0.0',
            'behavior_digest' => 'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
            'contracts' => [
                'input' => [
                    'type' => 'object',
                ],
            ],
            'logic' => [
                'type' => 'steps',
                'version' => 1,
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => [
                            'status' => 'ok',
                        ],
                    ],
                ],
            ],
            'outputs' => [
                'type' => 'assessment-result',
            ],
            'policies' => [
                'visibility' => 'public',
            ],
        ],
    );

    $response->assertCreated();

    $response->assertJsonStructure([
        'id',
        'blueprint_id',
        'number',
        'contracts',
        'logic',
        'outputs',
        'policies',
        'frozen',
    ]);

    $response->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'behavior_digest',
        'sha256:fa86626d8f2b1e31d24d0ebd1c3e9f7efb5cecad73b12624dbf379f04ce120b7',
    );

    $response->assertJsonPath(
        'contracts.input.type',
        'object',
    );

    $response->assertJsonPath(
        'logic.type',
        'steps',
    );

    $response->assertJsonPath(
        'logic.version',
        1,
    );

    $response->assertJsonPath(
        'logic.steps.0.type',
        'return',
    );

    $response->assertJsonPath(
        'logic.steps.0.data.status',
        'ok',
    );

    $response->assertJsonPath(
        'outputs.type',
        'assessment-result',
    );

    $response->assertJsonPath(
        'policies.visibility',
        'public',
    );

    $response->assertJsonPath(
        'frozen',
        false,
    );

    $revisionId = $response->json('id');

    expect($revisionId)->not->toBeNull();

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => $revisionId,
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.0.0',
        'behavior_digest' =>
            'sha256:fa86626d8f2b1e31d24d0ebf1c3e9f7efb5cecad73b12624dbf379f04ce120b7',
        'frozen' => false,
    ]);
});

it('returns 404 when adding a revision to a missing blueprint', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $user = User::factory()->create();

$response = $this
    ->actingAs($user)
    ->postJson(
        "/api/blueprints/{$blueprintId}/revisions",
        [
            'number' => '1.0.0',
            'contracts' => [
                'input' => [
                    'type' => 'object',
                ],
            ],
            'logic' => [
                'type' => 'steps',
                'version' => 1,
                'steps' => [
                    [
                        'type' => 'return',
                        'data' => [
                            'status' => 'ok',
                        ],
                    ],
                ],
            ],
            'outputs' => [
                'type' => 'assessment-result',
            ],
            'policies' => [
                'visibility' => 'public',
            ],
        ],
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when required revision fields are missing', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-validation',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

$response = $this
    ->actingAs($user)
    ->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions",
        [],
    );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'number',
        'contracts',
        'logic',
        'outputs',
        'policies',
    ]);
});


it('returns structured validation errors for an invalid behavior contract', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-invalid-behavior',
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
            "/api/blueprints/{$blueprint->id()}/revisions",
            [
                'number' => '1.0.0',
                    'contracts' => [
                    'input' => [
                        'type' => 'object',
                    ],
                ],
                'logic' => [
                    'type' => 'invalid',
                    'version' => 1,
                    'steps' => [],
                ],
                'outputs' => [
                    'type' => 'assessment-result',
                ],
                'policies' => [
                    'visibility' => 'public',
                ],
            ],
        );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Behavior contract validation failed.',
        )
        ->assertJson([
            'errors' => [
                'root.type' => 'The type attribute must equal "steps".',
            ],
        ]);
});

it('returns 422 for a domain lifecycle violation', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-sunset',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revision = (new \App\Application\Blueprint\Commands\AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new \App\Application\Behavior\BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => ['status' => 'ok'],
                ],
            ],
        ],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    (new \App\Application\Blueprint\Commands\FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new \App\Application\Blueprint\Commands\PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new \App\Application\Blueprint\Commands\ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    (new \App\Application\Blueprint\Commands\DeprecateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    (new \App\Application\Blueprint\Commands\SunsetBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
            [
                'number' => '1.1.0',
                    'contracts' => ['input' => ['type' => 'object']],
                'logic' => [
                    'type' => 'steps',
                    'version' => 1,
                    'steps' => [
                        [
                            'type' => 'return',
                            'data' => ['status' => 'ok'],
                        ],
                    ],
                ],
                'outputs' => ['type' => 'assessment-result'],
                'policies' => ['visibility' => 'public'],
            ],
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'A sunset Blueprint cannot receive new Revisions.',
        ]);
});

it('forbids a user from adding a revision to another user blueprint', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'revision-owner-protected',
        namespace: 'skillvlt.edu.revisions',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
            [
                'number' => '1.0.0',
                    'contracts' => [
                    'input' => [
                        'type' => 'object',
                    ],
                ],
                'logic' => [
                    'type' => 'steps',
                    'version' => 1,
                    'steps' => [
                        [
                            'type' => 'return',
                            'data' => [
                                'status' => 'ok',
                            ],
                        ],
                    ],
                ],
                'outputs' => [
                    'type' => 'assessment-result',
                ],
                'policies' => [
                    'visibility' => 'public',
                ],
            ],
        );

    $response->assertForbidden();
});

it('rejects unauthenticated revision creation', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/revisions",
        [],
    );

    $response->assertUnauthorized();
});

it('allows adding a revision to a deprecated blueprint', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'revision-deprecated-api',
        namespace: 'skillvlt.edu.revisions',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $revisionOne = (new \App\Application\Blueprint\Commands\AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new \App\Application\Behavior\BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => ['status' => 'ok'],
                ],
            ],
        ],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    (new \App\Application\Blueprint\Commands\FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revisionOne->id(),
    );

    (new \App\Application\Blueprint\Commands\PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revisionOne->id(),
    );

    (new \App\Application\Blueprint\Commands\ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $this->actingAs($user)
        ->postJson("/api/blueprints/{$blueprint->id()}/deprecate")
        ->assertSuccessful();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
            [
                'number' => '1.1.0',
                    'contracts' => [
                    'input' => [
                        'type' => 'object',
                    ],
                ],
                'logic' => [
                    'type' => 'steps',
                    'version' => 1,
                    'steps' => [
                        [
                            'type' => 'return',
                            'data' => [
                                'status' => 'updated',
                            ],
                        ],
                    ],
                ],
                'outputs' => [
                    'type' => 'assessment-result',
                ],
                'policies' => [
                    'visibility' => 'public',
                ],
            ],
        );

    $response
        ->assertCreated()
        ->assertJsonPath('number', '1.1.0')
        ->assertJsonPath('frozen', false);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'deprecated',
    ]);

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => $response->json('id'),
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.1.0',
        'frozen' => false,
    ]);
});


it('rejects a revision number lower than the latest revision through the HTTP API', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'revision-order-api',
        namespace: 'skillvlt.edu.revisions',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $payload = [
        'number' => '1.1.0',
        'behavior_digest' =>
            'sha256:1111111111111111111111111111111111111111111111111111111111111111',
        'contracts' => [
            'input' => [
                'type' => 'object',
            ],
        ],
        'logic' => [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => [
                        'status' => 'ok',
                    ],
                ],
            ],
        ],
        'outputs' => [
            'type' => 'assessment-result',
        ],
        'policies' => [
            'visibility' => 'public',
        ],
    ];

    $this->actingAs($user)
        ->postJson("/api/blueprints/{$blueprint->id()}/revisions", $payload)
        ->assertCreated();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
            [
                ...$payload,
                'number' => '1.0.0',
                ],
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' =>
                'A new Revision number must be greater than the latest Revision number.',
        ]);

    $this->assertDatabaseCount('blueprint_revisions', 1);

    $this->assertDatabaseHas('blueprint_revisions', [
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.1.0',
    ]);
});
