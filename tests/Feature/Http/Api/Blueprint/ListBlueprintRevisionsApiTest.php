<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('lists blueprint revisions through the HTTP API', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-revisions',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $firstRevision = (new AddBlueprintRevision($repository))->handle(
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
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $secondRevision = (new AddBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
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

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
        );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'blueprint_id',
                'number',
                'parent_revision_id',
                'behavior_digest',
                'contracts',
                'logic',
                'outputs',
                'policies',
                'frozen',
            ],
        ],
    ]);

    $response->assertJsonCount(
        2,
        'data',
    );

    $response->assertJsonPath(
        'data.0.id',
        (string) $firstRevision->id(),
    );

    $response->assertJsonPath(
        'data.0.blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'data.0.number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'data.0.parent_revision_id',
        null,
    );

    $response->assertJsonPath(
        'data.0.behavior_digest',
        'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    );

    $response->assertJsonPath(
        'data.0.frozen',
        false,
    );

    $response->assertJsonPath(
        'data.1.id',
        (string) $secondRevision->id(),
    );

    $response->assertJsonPath(
        'data.1.blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'data.1.number',
        '1.1.0',
    );

    $response->assertJsonPath(
        'data.1.behavior_digest',
        'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    );

    $response->assertJsonPath(
        'data.1.frozen',
        false,
    );
});

it('returns an empty revision list when the blueprint has no revisions', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-no-revisions',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [],
    );

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
        );

    $response->assertSuccessful();

    $response->assertJson([
        'data' => [],
    ]);
});

it('returns 404 when listing revisions for a missing blueprint', function () {
    $user = User::factory()->create();

    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/blueprints/{$blueprintId}/revisions",
        );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('forbids a user from listing revisions of another user blueprint', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'protected-revisions',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    (new AddBlueprintRevision($repository))->handle(
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
            'steps' => [
                'validate',
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
        ->actingAs($otherUser)
        ->getJson(
            "/api/blueprints/{$blueprint->id()}/revisions",
        );

    $response->assertForbidden();
});

it('rejects unauthenticated blueprint revision listing', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this->getJson(
        "/api/blueprints/{$blueprintId}/revisions",
    );

    $response->assertUnauthorized();
});