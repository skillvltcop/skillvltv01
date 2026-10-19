<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\DeprecateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Application\Blueprint\Commands\SunsetBlueprint;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionId;

it('rejects missing blueprints consistently across blueprint commands', function () {
    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->times(5)
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn(null);

    $blueprintId = (string) BlueprintId::generate();
    $revisionId = (string) RevisionId::generate();

    $commands = [
        fn () => (new AddBlueprintRevision(
            $repository,
            new BehaviorContractValidator(),
        ))->handle(
            blueprintId: $blueprintId,
            number: '1.0.0',
            contracts: [],
            logic: [],
            outputs: [],
            policies: [],
        ),
        fn () => (new FreezeBlueprintRevision($repository))->handle(
            blueprintId: $blueprintId,
            revisionId: $revisionId,
        ),
        fn () => (new PromoteBlueprintRevision($repository))->handle(
            blueprintId: $blueprintId,
            revisionId: $revisionId,
        ),
        fn () => (new DeprecateBlueprint($repository))->handle(
            blueprintId: $blueprintId,
        ),
        fn () => (new SunsetBlueprint($repository))->handle(
            blueprintId: $blueprintId,
        ),
    ];

    foreach ($commands as $command) {
        expect($command)->toThrow(
            RuntimeException::class,
            'Blueprint not found.',
        );
    }
});

it('rejects freezing a revision that does not exist on the blueprint', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('missing-revision-blueprint'),
        namespace: new BlueprintNamespace('skillvlt.edu.testing'),
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

    expect(fn () => (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) RevisionId::generate(),
    ))->toThrow(
        RuntimeException::class,
        'Blueprint revision not found.',
    );
});
