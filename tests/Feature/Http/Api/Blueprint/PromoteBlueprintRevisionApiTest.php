<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('promotes a frozen blueprint revision through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-promote',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/promote",
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

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
        'current_revision_id' => (string) $revision->id(),
    ]);

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => (string) $revision->id(),
        'blueprint_id' => (string) $blueprint->id(),
        'frozen' => true,
    ]);
});

it('returns 404 when promoting a revision for a missing blueprint', function () {
    $blueprintId = BlueprintId::generate();
    $revisionId = RevisionId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/revisions/{$revisionId}/promote",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 404 when promoting a missing revision', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-promote-missing',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $missingRevisionId = RevisionId::generate();

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$missingRevisionId}/promote",
    );
$response->dump();
    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint revision not found.',
    ]);
});

it('rejects promoting an unfrozen revision', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-promote-unfrozen',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = (new AddBlueprintRevision($repository))->handle(
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

    expect($revision->isFrozen())->toBeFalse();

    $response = $this->postJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}/promote",
    );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'A Revision must be frozen before it can become current.',
    ]);
});