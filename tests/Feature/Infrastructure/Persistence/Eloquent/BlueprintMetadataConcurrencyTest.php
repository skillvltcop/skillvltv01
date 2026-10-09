<?php

use App\Domain\Blueprint\Entities\Blueprint as DomainBlueprint;
use App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a stale blueprint metadata overwrite', function () {
    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('concurrent-metadata-test'),
        namespace: new BlueprintNamespace('skillvlt.edu.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'education',
            ],
        ],
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

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->updateMetadata([
        'taxonomy' => [
            'domain' => 'updated',
        ],
    ]);

    $repository->save($currentWriter);

    $staleWriter->freezeRevision($revision->id());

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(ConcurrentBlueprintRevisionException::class);

    $persisted = $repository->find($blueprint->id());

    expect($persisted->metadata())
        ->toBe([
            'taxonomy' => [
                'domain' => 'updated',
            ],
        ]);
});

it('rejects a stale lifecycle write after a newer transition to sunset', function () {
    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('concurrent-lifecycle-test'),
        namespace: new BlueprintNamespace('skillvlt.edu.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'education',
            ],
        ],
    );

    $revision = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $blueprint->freezeRevision($revision->id());
    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->sunset();
    $repository->save($currentWriter);

    $staleWriter->deprecate();

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(ConcurrentBlueprintRevisionException::class);

    $persisted = $repository->find($blueprint->id());

    expect($persisted->lifecycleStatus()->value)->toBe('sunset');
});

it('rejects a stale revision append after another writer adds a newer revision', function () {
    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('concurrent-revision-test'),
        namespace: new BlueprintNamespace('skillvlt.edu.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('c', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('d', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $repository->save($currentWriter);

    // This aggregate is stale and still believes 1.0.0 is the latest Revision.
    $staleWriter->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('e', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(ConcurrentBlueprintRevisionException::class);

    $persisted = $repository->find($blueprint->id());

    expect($persisted->revisions())->toHaveCount(2)
        ->and((string) $persisted->latestRevision()->number())->toBe('1.1.0')
        ->and((string) $persisted->latestRevision()->behaviorDigest())
        ->toBe('sha256:' . str_repeat('d', 64));
});


it('rejects a stale promotion after another writer promotes a newer revision', function () {
    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('concurrent-promotion-test'),
        namespace: new BlueprintNamespace('skillvlt.edu.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $first = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('f', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $blueprint->freezeRevision($first->id());
    $blueprint->promoteRevision($first->id());
    $blueprint->activate();

    $second = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('a', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $blueprint->freezeRevision($second->id());

    $third = $blueprint->addRevision(
        number: new RevisionNumber('1.2.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('b', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $blueprint->freezeRevision($third->id());

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->promoteRevision($third->id());
    $repository->save($currentWriter);

    // The stale writer tries to promote 1.1.0 after 1.2.0 became current.
    $staleWriter->promoteRevision($second->id());

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(ConcurrentBlueprintRevisionException::class);

    $persisted = $repository->find($blueprint->id());

    expect((string) $persisted->currentRevision()->number())->toBe('1.2.0');
});

it('rejects a stale revision promotion after another writer sunsets the Blueprint', function () {
    $blueprint = DomainBlueprint::create(
        canonicalName: new CanonicalName('concurrent-sunset-promotion-test'),
        namespace: new BlueprintNamespace('skillvlt.edu.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $first = $blueprint->addRevision(
        number: new RevisionNumber('1.0.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('c', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $blueprint->freezeRevision($first->id());
    $blueprint->promoteRevision($first->id());
    $blueprint->activate();

    $next = $blueprint->addRevision(
        number: new RevisionNumber('1.1.0'),
        behaviorDigest: new BehaviorDigest(
            'sha256:' . str_repeat('d', 64),
        ),
        contracts: [],
        logic: [],
        outputs: [],
        policies: [],
    );
    $blueprint->freezeRevision($next->id());

    $repository = new EloquentBlueprintRepository();
    $repository->save($blueprint);

    $staleWriter = $repository->find($blueprint->id());
    $currentWriter = $repository->find($blueprint->id());

    expect($staleWriter)->not->toBeNull();
    expect($currentWriter)->not->toBeNull();

    $currentWriter->sunset();
    $repository->save($currentWriter);

    // This stale aggregate still believes the Blueprint is active.
    $staleWriter->promoteRevision($next->id());

    expect(fn () => $repository->save($staleWriter))
        ->toThrow(ConcurrentBlueprintRevisionException::class);

    $persisted = $repository->find($blueprint->id());

    expect($persisted->lifecycleStatus()->value)->toBe('sunset')
        ->and((string) $persisted->currentRevision()->number())->toBe('1.0.0');
});
