<?php

use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

it('promotes a frozen revision to current and persists the blueprint', function () {
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

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new PromoteBlueprintRevision($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    expect($result)
        ->toBe($revision);

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe((string) $revision->id());

    expect($blueprint->latestRevision())
        ->toBe($revision);
});

it('cannot promote an unfrozen revision', function () {
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

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository->shouldNotReceive('save');

    $command = new PromoteBlueprintRevision($repository);

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    ))->toThrow(
        \DomainException::class,
        'A Revision must be frozen before it can become current.'
    );

    expect($blueprint->currentRevision())
        ->toBeNull();
});

it('cannot promote a revision that does not belong to the blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->once()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository->shouldNotReceive('save');

    $command = new PromoteBlueprintRevision($repository);

    $unknownRevisionId = (string) \App\Domain\Blueprint\ValueObjects\RevisionId::generate();

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: $unknownRevisionId,
    ))->toThrow(
        \RuntimeException::class,
        'Blueprint revision not found.'
    );

    expect($blueprint->currentRevision())
        ->toBeNull();
});

it('promotes a frozen revision while the blueprint is active', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-rubric-core'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $firstRevision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        ),
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $firstRevision->freeze();

    $blueprint->promoteRevision($firstRevision->id());
    $blueprint->activate();

    $secondRevision = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        ),
        contracts: ['input' => ['type' => 'object']],
        logic: ['steps' => ['validate', 'score']],
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $secondRevision->freeze();

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

    $command = new PromoteBlueprintRevision($repository);

    $result = $command->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $secondRevision->id(),
    );

    expect($result)
        ->toBe($secondRevision);

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);

    expect($blueprint->currentRevision())
        ->toBe($secondRevision);

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);
});