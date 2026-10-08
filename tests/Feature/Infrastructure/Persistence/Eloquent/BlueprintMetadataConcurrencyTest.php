<?php

use AppDomainBlueprint\Entities\Blueprint as DomainBlueprint;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException;
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
