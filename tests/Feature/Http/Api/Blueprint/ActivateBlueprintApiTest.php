<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Application\Behavior\BehaviorContractValidator;
use App\Models\User;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('activates a blueprint with a frozen current revision through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-activate',
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
                        'status' => 'ok',
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

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
    );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'id',
        'canonical_name',
        'namespace',
        'ownership',
        'metadata',
        'lifecycle_status',
        'current_revision_id',
    ]);

    $response->assertJsonPath(
        'id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'canonical_name',
        'assessment-rubric-activate',
    );

    $response->assertJsonPath(
        'namespace',
        'skillvlt.edu.assessment',
    );

    $response->assertJsonPath(
        'lifecycle_status',
        'active',
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'active',
        'current_revision_id' => (string) $revision->id(),
    ]);
});

it('returns 404 when activating a missing blueprint', function () {

    $blueprintId = BlueprintId::generate();

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/activate",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when activating a blueprint without a current revision', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-no-current',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
        'type' => 'user',
        'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
    );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'A Blueprint cannot become active without a Revision.',
    ]);
});

it('forbids activating a system-owned blueprint through the user API', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-owned-activate',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

it('forbids a user from activating another user-owned blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'another-users-blueprint',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $this->actingAs($actor);

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

it('forbids a user from adding a revision to a system-owned blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-revision-protected',
        namespace: 'skillvlt.edu.revisions',
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
            [
                'number' => '1.0.0',
                'behavior_digest' =>
                    'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
                'contracts' => [
                    'input' => [
                        'type' => 'object',
                    ],
                ],
                'logic' => [
                    'steps' => [
                        ['type' => 'validate'],
                        ['type' => 'score'],
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

it('rejects activating a deprecated blueprint', function () {
    $repository = new EloquentBlueprintRepository();
    $user = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-reactivate-deprecated',
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
                        'status' => 'ok',
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

    $blueprint = $repository->find(
        new BlueprintId((string) $blueprint->id()),
    );

    $blueprint->activate();
    $blueprint->deprecate();

    $repository->save($blueprint);

    $response = $this
        ->actingAs($user)
        ->postJson("/api/blueprints/{$blueprint->id()}/activate");

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' =>
                'Invalid Blueprint lifecycle transition: deprecated → active.',
        ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'deprecated',
    ]);
});

it('rejects unauthenticated blueprint activation', function () {
    $repository = new EloquentBlueprintRepository();
    $owner = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'unauthenticated-activate',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/activate",
    );

    $response->assertUnauthorized();
});
