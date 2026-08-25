<?php

use App\Application\Execution\Runtime\BehaviorRunner;

it('runs the declared steps and returns their execution trace', function () {
    $runner = new BehaviorRunner();

    $result = $runner->run(
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [
            'locale' => 'ar',
        ],
    );

    expect($result)
        ->toBe([
            'steps' => [
                'validate',
                'score',
            ],
        ]);
});

it('returns an empty execution trace when steps are declared but empty', function () {
    $runner = new BehaviorRunner();

    $result = $runner->run(
        logic: [
            'steps' => [],
        ],
        input: [],
        context: [],
    );

    expect($result)
        ->toBe([
            'steps' => [],
        ]);
});

it('executes declared steps in order', function () {
    $runner = new BehaviorRunner();

    $result = $runner->run(
        logic: [
            'steps' => [
                'validate',
                'score',
                'finalize',
            ],
        ],
        input: [],
        context: [],
    );

    expect($result['steps'])
        ->toBe([
            'validate',
            'score',
            'finalize',
        ]);
});

it('returns an execution trace for the declared steps', function () {
    $runner = new BehaviorRunner();

    $result = $runner->run(
        logic: [
            'steps' => [
                'validate',
                'score',
            ],
        ],
        input: [],
        context: [],
    );

    expect($result)
        ->toHaveKey('steps')
        ->and($result['steps'])
        ->toBe([
            'validate',
            'score',
        ]);
});

it('does not mutate the declared logic', function () {
    $runner = new BehaviorRunner();

    $logic = [
        'steps' => [
            'validate',
            'score',
        ],
    ];

    $runner->run(
        logic: $logic,
        input: [],
        context: [],
    );

    expect($logic)
        ->toBe([
            'steps' => [
                'validate',
                'score',
            ],
        ]);
});

it('rejects invalid behavior logic', function () {
    $runner = new BehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'invalid' => true,
        ],
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Behavior logic must define steps.'
    );
});

it('rejects behavior steps that are not strings', function () {
    $runner = new BehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'steps' => [
                'validate',
                ['type' => 'score'],
            ],
        ],
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Behavior logic steps must contain only strings.'
    );
});