<?php

use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\Enums\LifecycleStatus;

it('freezes an existing blueprint revision and persists the blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        ),
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    expect($revision->isFrozen())
        ->toBeFalse();

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new FreezeBlueprintRevision($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    expect($result)
        ->toBe($revision);

    expect($revision->isFrozen())
        ->toBeTrue();
});

it('cannot freeze an already frozen revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        ),
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'steps' => ['validate'],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $revision->freeze();

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository->shouldNotReceive('save');

    $command = new FreezeBlueprintRevision($repository);

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    ))->toThrow(
        \DomainException::class,
        'Revision is already frozen.'
    );
});

it('cannot freeze a revision on a sunset blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-sunset-freeze'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $revision->freeze();

    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();
    $blueprint->sunset();

    // Add a new draft revision before sunset is impossible now,
    // so reconstitute a sunset blueprint with an unfrozen revision.
    $draftRevision = BlueprintRevision::reconstitute(
        id: RevisionId::generate(),
        number: new RevisionNumber('1.1.0'),
        parentRevisionId: $revision->id(),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
        frozen: false,
    );

    $sunsetBlueprint = Blueprint::reconstitute(
        id: $blueprint->id(),
        canonicalName: $blueprint->canonicalName(),
        namespace: $blueprint->namespace(),
        ownership: $blueprint->ownership(),
        metadata: $blueprint->metadata(),
        lifecycleStatus: LifecycleStatus::SUNSET,
        currentRevisionId: $revision->id(),
        revisions: [
            (string) $revision->id() => $revision,
            (string) $draftRevision->id() => $draftRevision,
        ],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($sunsetBlueprint);

    $repository->shouldNotReceive('save');

    $command = new FreezeBlueprintRevision($repository);

    expect(fn () => $command->handle(
        blueprintId: (string) $sunsetBlueprint->id(),
        revisionId: (string) $draftRevision->id(),
    ))->toThrow(
        DomainException::class,
        'A sunset Blueprint cannot freeze a Revision.'
    );
});

