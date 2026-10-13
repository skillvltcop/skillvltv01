<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

function calculateStepRunner(): BehaviorRunner
{
    return new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );
}

function calculateBehavior(string $operation, mixed $left, mixed $right): array
{
    return [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'calculate',
                'operation' => $operation,
                'left' => $left,
                'right' => $right,
                'assign_to' => 'result',
            ],
            [
                'type' => 'return',
                'data' => [
                    'result' => '{state.result}',
                ],
            ],
        ],
    ];
}

it('supports arithmetic calculate operations', function ($operation, $left, $right, $expected) {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior($operation, $left, $right),
        input: [
            'left' => 10,
            'right' => 7,
        ],
    );

    expect(abs((float) $result['result'] - (float) $expected))->toBeLessThan(0.000001);
})->with([
    ['add', 'input.left', 'input.right', 17],
    ['subtract', 'input.left', 'input.right', 3],
    ['multiply', 'input.left', 'input.right', 70],
    ['divide', 'input.left', 'input.right', 10 / 7],
]);

it('supports numeric literals as calculate operands', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => 0.7],
    );

    expect((float) $result['result'])->toBe(70.0);
});

it('supports chaining calculations through state', function () {
    $runner = calculateStepRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'calculate',
                'operation' => 'divide',
                'left' => 'input.correct',
                'right' => 'input.total',
                'assign_to' => 'ratio',
            ],
            [
                'type' => 'calculate',
                'operation' => 'multiply',
                'left' => 'state.ratio',
                'right' => 100,
                'assign_to' => 'percentage',
            ],
            [
                'type' => 'return',
                'data' => [
                    'percentage' => '{state.percentage}',
                ],
            ],
        ],
    ];

    expect((float) $runner->run(
        logic: $logic,
        input: [
            'correct' => 14,
            'total' => 20,
        ],
    )['percentage'])->toBe(70.0);
});

it('rejects unsupported calculate operations', function () {
    $runner = calculateStepRunner();

    expect(fn () => $runner->run(
        logic: calculateBehavior('modulo', 10, 3),
        input: [],
    ))->toThrow(InvalidBehaviorContractException::class);
});

it('rejects non-numeric calculate operands at runtime', function () {
    $runner = calculateStepRunner();

    expect(fn () => $runner->run(
        logic: calculateBehavior('add', 'input.left', 5),
        input: ['left' => 'five'],
    ))->toThrow(
        DomainException::class,
        'Calculation requires numeric operands.'
    );
});

it('rejects division by zero', function () {
    $runner = calculateStepRunner();

    expect(fn () => $runner->run(
        logic: calculateBehavior('divide', 10, 0),
        input: [],
    ))->toThrow(
        DomainException::class,
        'Division by zero is not allowed.'
    );
});

it('rejects invalid calculate operands before runtime execution', function () {
    $runner = calculateStepRunner();

    expect(fn () => $runner->run(
        logic: calculateBehavior('add', 'input.left', 'invalid.path'),
        input: ['left' => 10],
    ))->toThrow(InvalidBehaviorContractException::class);
});

it('normalizes numeric-string operands before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('divide', 'input.score', 'input.max_score'),
        input: ['score' => '7.5', 'max_score' => '10'],
    );

    expect((float) $result['result'])->toBe(0.75);
});

it('normalizes a numeric-string operand when paired with a numeric literal', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '0.75'],
    );

    expect((float) $result['result'])->toBe(75.0);
});

it('rejects non-numeric string operands at contract validation', function ($operand) {
    $runner = calculateStepRunner();

    expect(fn () => $runner->run(
        logic: calculateBehavior('add', $operand, 1),
        input: [],
    ))->toThrow(InvalidBehaviorContractException::class);
})->with([
    'plain text' => 'not-a-number',
    'empty string' => '',
    'whitespace' => ' ',
]);

it('normalizes scientific-notation numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '7.5e-1'],
    );

    expect((float) $result['result'])->toBe(75.0);
});

it('normalizes scientific-notation numeric strings with positive exponents', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '7.5E+1'],
    );

    expect((float) $result['result'])->toBe(750.0);
});

it('normalizes negative numeric-string operands before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '-0.75'],
    );

    expect((float) $result['result'])->toBe(-75.0);
});

it('normalizes negative scientific-notation numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '-7.5e-1'],
    );

    expect((float) $result['result'])->toBe(-75.0);
});

it('normalizes negative scientific-notation strings with positive exponents', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '-7.5E+1'],
    );

    expect((float) $result['result'])->toBe(-750.0);
});

it('normalizes negative integer numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '-7'],
    );

    expect((float) $result['result'])->toBe(-70.0);
});

it('normalizes positive integer numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '7'],
    );

    expect((float) $result['result'])->toBe(70.0);
});

it('normalizes zero integer numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '0'],
    );

    expect((float) $result['result'])->toBe(0.0);
});

it('normalizes leading-zero numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 10),
        input: ['score' => '007'],
    );

    expect((float) $result['result'])->toBe(70.0);
});

it('normalizes leading-zero decimal numeric strings before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '00.75'],
    );

    expect((float) $result['result'])->toBe(75.0);
});

it('normalizes leading-zero decimal fractions before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 1000),
        input: ['score' => '000.0075'],
    );

    expect((float) $result['result'])->toBe(7.5);
});

it('normalizes leading-zero decimals with trailing zeros before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '000.7500'],
    );

    expect((float) $result['result'])->toBe(75.0);
});

it('normalizes leading-zero scientific-notation decimals before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '00.75e0'],
    );

    expect((float) $result['result'])->toBe(75.0);
});

it('normalizes leading-zero negative decimals before arithmetic', function () {
    $runner = calculateStepRunner();

    $result = $runner->run(
        logic: calculateBehavior('multiply', 'input.score', 100),
        input: ['score' => '-00.75'],
    );

    expect((float) $result['result'])->toBe(-75.0);
});
