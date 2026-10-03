<?php

use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

function validBehaviorLogic(): array
{
    return [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'evaluate_rule',
                'condition' => [
                    'field' => 'input.score',
                    'operator' => 'gte',
                    'value' => 10,
                ],
                'assign_to' => 'result_status',
                'true_value' => 'pass',
                'false_value' => 'fail',
            ],
            [
                'type' => 'return',
                'data' => [
                    'status' => '{state.result_status}',
                ],
            ],
        ],
    ];
}

it('adds a revision to an existing blueprint and persists it', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
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

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    $revision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: validBehaviorLogic(),
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    expect($revision)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\BlueprintRevision::class
        );

    expect((string) $revision->number())
        ->toBe('1.0.0');

    expect((string) $revision->behaviorDigest())
        ->toBe(
            'sha256:0a4f71e3089d5966be9079cbd5363ee324521cdd689840c06f7df6b489476232'
        );

    expect($blueprint->currentRevision())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($revision);
});

it('links a new revision to the previous revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->twice()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->twice()
        ->with($blueprint);

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    $firstRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: validBehaviorLogic(),
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        contracts: ['input' => ['type' => 'object']],
        logic: validBehaviorLogic(),
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($secondRevision->parentRevisionId())
        ->not->toBeNull();

    expect((string) $secondRevision->parentRevisionId())
        ->toBe((string) $firstRevision->id());

    expect($blueprint->currentRevision())
        ->toBeNull();

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);
});

it('keeps the current revision unchanged when adding a new revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [],
    );

    $repository = Mockery::mock(BlueprintRepository::class);

    $repository
        ->shouldReceive('find')
        ->twice()
        ->with(Mockery::type(BlueprintId::class))
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->twice()
        ->with($blueprint);

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    $firstRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: ['input' => ['type' => 'object']],
        logic: validBehaviorLogic(),
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    $firstRevision->freeze();

    $blueprint->promoteRevision(
        $firstRevision->id()
    );

    $blueprint->activate();

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        contracts: ['input' => ['type' => 'object']],
        logic: validBehaviorLogic(),
        outputs: ['type' => 'assessment-result'],
        policies: ['visibility' => 'public'],
    );

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);

    expect($blueprint->currentRevision())
        ->not->toBeNull();

    expect((string) $blueprint->currentRevision()->id())
        ->toBe((string) $firstRevision->id());
});

it('allows adding a new revision to an active blueprint without changing the current revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
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
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: validBehaviorLogic(),
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    $firstRevision->freeze();

    $blueprint->promoteRevision(
        $firstRevision->id()
    );

    $blueprint->activate();

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

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    $secondRevision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: validBehaviorLogic(),
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    expect($secondRevision)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\BlueprintRevision::class
        );

    expect($blueprint->lifecycleStatus())
        ->toBe(\App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE);

    expect($blueprint->latestRevision())
        ->toBe($secondRevision);

    expect($blueprint->currentRevision())
        ->toBe($firstRevision);

    expect((string) $blueprint->currentRevision()->number())
        ->toBe('1.0.0');

    expect((string) $blueprint->latestRevision()->number())
        ->toBe('1.1.0');
});

it('rejects an invalid behavior contract before persistence', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
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

    $repository
        ->shouldReceive('save')
        ->never();

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    expect(fn () => $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'format_template',
                    'template' => '{user.name}',
                    'assign_to' => 'message',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'message' => '{state.message}',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    ))->toThrow(InvalidBehaviorContractException::class);
});

it('validates behavior before creating the revision', function () {
    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'behavior-validation-test'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.test'
        ),
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
        ->andReturn($blueprint);

    $repository
        ->shouldReceive('save')
        ->once()
        ->with($blueprint);

    $command = new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
        behaviorDigestCalculator: new \App\Application\Behavior\BehaviorDigestCalculator(),
    );

    $revision = $command->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => ['type' => 'object'],
        ],
        logic: validBehaviorLogic(),
        outputs: [
            'type' => 'assessment-result',
        ],
        policies: [
            'visibility' => 'public',
        ],
    );

    expect($revision)
        ->toBeInstanceOf(
            \App\Domain\Blueprint\Entities\BlueprintRevision::class
        );
});