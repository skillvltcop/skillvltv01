<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Execution\InputContractDefinitionValidator;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;

it('rejects an invalid required input declaration before persisting a revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('invalid-input-contract'),
        namespace: new BlueprintNamespace('skillvlt.test'),
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

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        inputContractDefinitionValidator: new InputContractDefinitionValidator(),
    );

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => [
                'type' => 'object',
                'required' => ['score', 123],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                ['type' => 'return', 'data' => ['result' => 'ok']],
            ],
        ],
        outputs: [],
        policies: [],
    ))->toThrow(
        DomainException::class,
        'Input contract required must contain only non-empty strings.',
    );
});
