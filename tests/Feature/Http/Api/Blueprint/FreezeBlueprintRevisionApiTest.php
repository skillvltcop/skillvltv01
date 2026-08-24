<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('freezes a blueprint revision through the HTTP API', function () {
    $user = User::factory()->create();
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-freeze',
        namespace: 'skillvlt.edu.assessment',
ownership: [
    'type' => 'user',
    'id' => (string) $user->id,
],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
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

    expect($revision->isFrozen())->toBeFalse();

$response = $this
    ->actingAs($user)
    ->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/freeze",
    );

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'id',
        'blueprint_id',
        'number',
        'behavior_digest',
        'contracts',
        'logic',
        'outputs',
        'policies',
        'frozen',
    ]);

    $response->assertJsonPath(
        'id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'blueprint_id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'frozen',
        true,
    );

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => (string) $revision->id(),
        'blueprint_id' => (string) $blueprint->id(),
        'frozen' => true,
    ]);
});

it('returns 404 when freezing a revision for a missing blueprint', function () {
    $user = User::factory()->create();
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

$response = $this
    ->actingAs($user)
    ->postJson(
        "/api/blueprints/{$blueprintId}/revisions/{$revisionId}/freeze",
    );
    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 404 when freezing a missing revision', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-freeze-missing',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $missingRevisionId = RevisionId::generate();

$response = $this
    ->actingAs($user)
    ->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$missingRevisionId}/freeze",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint revision not found.',
    ]);
});

it('forbids a user from freezing another user blueprint revision', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'freeze-owner-protected',
        namespace: 'skillvlt.edu.freeze',
        ownership: [
            'type' => 'user',
            'id' => (string) $owner->id,
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
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
        ->actingAs($otherUser)
        ->postJson(
            "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/freeze",
        );

    $response->assertForbidden();
});

it('rejects unauthenticated revision freezing', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/revisions/{$revisionId}/freeze",
    );

    $response->assertUnauthorized();
});