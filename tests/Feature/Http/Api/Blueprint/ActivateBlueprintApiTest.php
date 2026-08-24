<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('activates a blueprint with a frozen current revision through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-activate',
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
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
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

    $response = $this->postJson(
        "/api/blueprints/{$blueprintId}/activate",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 422 when activating a blueprint without a current revision', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-no-current',
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

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'A Blueprint cannot become active without a Revision.',
    ]);
});
