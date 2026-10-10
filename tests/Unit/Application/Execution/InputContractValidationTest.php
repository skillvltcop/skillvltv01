<?php

use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\InputContractValidator;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

it('rejects missing required input fields before invoking the behavior runner', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('input-contract-validation'),
        namespace: new BlueprintNamespace('skillvlt.test'),
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
        contracts: [
            'input' => [
                'type' => 'object',
                'required' => ['score', 'max_score'],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => ['result' => 'ok'],
                ],
            ],
        ],
        outputs: [],
        policies: [],
    );

    $revision->freeze();
    $blueprint->promoteRevision($revision->id());
    $blueprint->activate();

    $runner = Mockery::mock(BehaviorRunner::class);
    $runner->shouldNotReceive('run');

    $engine = new ExecutionEngine(
        runner: $runner,
        inputContractValidator: new InputContractValidator(),
    );

    expect(fn () => $engine->execute(
        blueprint: $blueprint,
        revisionId: $revision->id(),
        input: ['score' => 8],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Input is missing required field(s): max_score.',
    );
});

it('allows input that contains every required field', function () {
    $validator = new InputContractValidator();

    expect(fn () => $validator->validate(
        [
            'input' => [
                'type' => 'object',
                'required' => ['score', 'max_score'],
            ],
        ],
        ['score' => 8, 'max_score' => 10],
    ))->not->toThrow(DomainException::class);
});

it('treats a present null value as present rather than as a missing required field', function () {
    $validator = new InputContractValidator();

    expect(fn () => $validator->validate(
        [
            'input' => [
                'type' => 'object',
                'required' => ['comment'],
            ],
        ],
        ['comment' => null],
    ))->not->toThrow(DomainException::class);
});

it('preserves compatibility when the input contract has no required list', function () {
    $validator = new InputContractValidator();

    expect(fn () => $validator->validate(
        ['input' => ['type' => 'object']],
        [],
    ))->not->toThrow(DomainException::class);
});

it('rejects malformed input contract definitions during execution', function (array $contracts) {
    $validator = new InputContractValidator();

    expect(fn () => $validator->validate($contracts, []))
        ->toThrow(DomainException::class);
})->with([
    'non-array input contract' => [
        ['input' => null],
    ],
    'associative required declaration' => [
        ['input' => ['required' => ['primary' => 'score']]],
    ],
    'non-string required field' => [
        ['input' => ['required' => ['score', 123]]],
    ],
]);
