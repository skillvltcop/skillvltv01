<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\ActivateBlueprint;
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

it('deprecates an active blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $user = User::factory()->create();

    $this->actingAs($user);

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-deprecate',
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

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/deprecate",
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
        'assessment-rubric-deprecate',
    );

    $response->assertJsonPath(
        'namespace',
        'skillvlt.edu.assessment',
    );

    $response->assertJsonPath(
        'lifecycle_status',
        'deprecated',
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'lifecycle_status' => 'deprecated',
        'current_revision_id' => (string) $revision->id(),
    ]);
});

it('returns 404 when deprecating a missing blueprint', function () {

    $blueprintId = BlueprintId::generate();

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/deprecate",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when deprecating a draft blueprint', function () {
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
        "/api/blueprints/{$blueprint->id()}/deprecate",
    );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Invalid Blueprint lifecycle transition: draft → deprecated.',
    ]);
});

it('forbids deprecating a system-owned blueprint through the user API', function () {
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
        "/api/blueprints/{$blueprint->id()}/deprecate",
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

it('forbids a user from deprecating another user-owned blueprint', function () {
    $repository = new EloquentBlueprintRepository();

    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'another-users-deprecate',
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

it('rejects unauthenticated blueprint deprecation', function () {
    $blueprintId = BlueprintId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/deprecate",
    );

    $response->assertUnauthorized();
});
