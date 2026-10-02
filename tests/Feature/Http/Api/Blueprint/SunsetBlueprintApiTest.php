<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\DeprecateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Application\Behavior\BehaviorContractValidator;
use App\Models\User;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('sunsets an active blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-sunset',
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

    $blueprint = $repository->find(
        new \App\Domain\Blueprint\ValueObjects\BlueprintId(
            (string) $blueprint->id(),
        ),
    );

    $blueprint->activate();

    $repository->save($blueprint);

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
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
        'assessment-rubric-sunset',
    );

    $response->assertJsonPath(
        'namespace',
        'skillvlt.edu.assessment',
    );

    $response->assertJsonPath(
        'lifecycle_status',
        LifecycleStatus::SUNSET->value,
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => LifecycleStatus::SUNSET->value,
        'current_revision_id' => (string) $revision->id(),
    ]);
});

it('sunsets a deprecated blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-deprecated-sunset',
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

    $blueprint = $repository->find(
        new BlueprintId((string) $blueprint->id()),
    );

    $blueprint->activate();
    $blueprint->deprecate();

    $repository->save($blueprint);

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
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
        'lifecycle_status',
        LifecycleStatus::SUNSET->value,
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => LifecycleStatus::SUNSET->value,
        'current_revision_id' => (string) $revision->id(),
    ]);
});

it('returns 404 when sunsetting a missing blueprint', function () {
    $blueprintId = BlueprintId::generate();

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/sunset",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when sunsetting a draft blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-draft-sunset',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
    );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' =>
            'Invalid Blueprint lifecycle transition: draft → sunset.',
    ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => LifecycleStatus::DRAFT->value,
    ]);
});

it('forbids a user from sunsetting a system-owned blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'system-owned-sunset',
        namespace: 'skillvlt.edu.system',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => LifecycleStatus::DRAFT->value,
    ]);
});

it('forbids a user from sunsetting another user-owned blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'another-users-blueprint-sunset',
        namespace: 'skillvlt.edu.system',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($actor)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/sunset",
        );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => LifecycleStatus::DRAFT->value,
    ]);
});

it('requires authentication to sunset a blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-auth-sunset',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => '999999',
        ],
        metadata: [],
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/sunset",
    );

    $response->assertUnauthorized();
});