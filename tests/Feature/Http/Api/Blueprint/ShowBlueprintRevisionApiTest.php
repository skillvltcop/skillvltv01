<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('retrieves a blueprint revision through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-revision-show',
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
                'required' => [
                    'student',
                ],
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

    $response = $this->getJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}",
    );

    $response->assertSuccessful();

    $response->assertJsonStructure([
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
        'parent_revision_id',
        null,
    );

    $response->assertJsonPath(
        'behavior_digest',
        'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    );

    $response->assertJsonPath(
        'contracts.input.type',
        'object',
    );

    $response->assertJsonPath(
        'contracts.input.required.0',
        'student',
    );

    $response->assertJsonPath(
        'logic.steps.0',
        'validate',
    );

    $response->assertJsonPath(
        'logic.steps.1',
        'score',
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
});

it('retrieves a frozen blueprint revision through the HTTP API', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-frozen-revision',
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
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
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

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    $response = $this->getJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$revision->id()}",
    );

    $response->assertSuccessful();

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
});

it('returns the parent revision id for a subsequent revision', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-parent-revision',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $firstRevision = (new AddBlueprintRevision($repository))->handle(
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

    $secondRevision = (new AddBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '2.0.0',
        behaviorDigest:
            'sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
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
            'type' => 'assessment-result-v2',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $response = $this->getJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$secondRevision->id()}",
    );

    $response->assertSuccessful();

    $response->assertJsonPath(
        'id',
        (string) $secondRevision->id(),
    );

    $response->assertJsonPath(
        'number',
        '2.0.0',
    );

    $response->assertJsonPath(
        'parent_revision_id',
        (string) $firstRevision->id(),
    );
});

it('returns 404 when the blueprint does not exist', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprintId = \App\Domain\Blueprint\ValueObjects\BlueprintId::generate();
    $revisionId = \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    $response = $this->getJson(
        "/api/blueprints/{$blueprintId}/revisions/{$revisionId}",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint not found.',
    ]);
});

it('returns 404 when the revision does not exist', function () {
    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-rubric-missing-revision',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $missingRevisionId =
        \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    $response = $this->getJson(
        "/api/blueprints/{$blueprint->id()}/revisions/{$missingRevisionId}",
    );

    $response->assertNotFound();

    $response->assertJson([
        'message' => 'Blueprint revision not found.',
    ]);
});