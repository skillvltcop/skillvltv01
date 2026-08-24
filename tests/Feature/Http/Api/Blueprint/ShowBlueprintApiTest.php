<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('retrieves a blueprint through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-show',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'assessment',
            ],
            'documentation' => [
                'description' => 'Assessment rubric blueprint.',
            ],
            'discovery' => [
                'tags' => [
                    'assessment',
                    'rubric',
                ],
            ],
            'lifecycle_metadata' => [
                'versioning' => 'semantic',
            ],
        ],
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

    $response = $this->getJson(
        "/api/blueprints/{$blueprint->id()}",
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
        'revisions',
    ]);

    $response->assertJsonPath(
        'id',
        (string) $blueprint->id(),
    );

    $response->assertJsonPath(
        'canonical_name',
        'assessment-rubric-show',
    );

    $response->assertJsonPath(
        'namespace',
        'skillvlt.edu.assessment',
    );

    $response->assertJsonPath(
        'ownership.type',
        'system',
    );

    $response->assertJsonPath(
        'ownership.id',
        'skillvlt',
    );

    $response->assertJsonPath(
        'lifecycle_status',
        'draft',
    );

    $response->assertJsonPath(
        'current_revision_id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'metadata.taxonomy.domain',
        'assessment',
    );

    $response->assertJsonPath(
        'metadata.documentation.description',
        'Assessment rubric blueprint.',
    );

    $response->assertJsonCount(
        1,
        'revisions',
    );

    $response->assertJsonPath(
        'revisions.0.id',
        (string) $revision->id(),
    );

    $response->assertJsonPath(
        'revisions.0.number',
        '1.0.0',
    );

    $response->assertJsonPath(
        'revisions.0.frozen',
        true,
    );
});

it('returns 404 when the blueprint does not exist', function () {
    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();

    $response = $this->getJson(
        "/api/blueprints/{$blueprintId}",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});