<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and persists a blueprint revision through the application layer', function () {
    $repository = new EloquentBlueprintRepository();

    $createBlueprint = new CreateBlueprint($repository);

    $blueprint = $createBlueprint->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'category' => 'education',
            'visibility' => 'public',
        ],
    );

    $command = new AddBlueprintRevision($repository);

    $revision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate', 'score'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    expect($revision)
        ->not->toBeNull();

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => (string) $revision->id(),
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.0.0',
        'behavior_digest' =>
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
    ]);
});

it('persists the parent revision relationship for a subsequent revision', function () {
    $repository = new EloquentBlueprintRepository();

    $createBlueprint = new CreateBlueprint($repository);

    $blueprint = $createBlueprint->handle(
        canonicalName: 'assessment-rubric-core',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $command = new AddBlueprintRevision($repository);

    $firstRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        behaviorDigest: 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        behaviorDigest: 'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate', 'score']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($secondRevision->parentRevisionId())
        ->not->toBeNull();

    expect((string) $secondRevision->parentRevisionId())
        ->toBe((string) $firstRevision->id());

    $this->assertDatabaseHas('blueprint_revisions', [
        'id' => (string) $secondRevision->id(),
        'blueprint_id' => (string) $blueprint->id(),
        'revision_number' => '1.1.0',
        'parent_revision_id' => (string) $firstRevision->id(),
    ]);

    $this->assertDatabaseHas('blueprints', [
        'id' => (string) $blueprint->id(),
    ]);
});