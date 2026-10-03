<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;
use App\Domain\Behavior\Exceptions\UnresolvablePathException;

function createBehaviorRunner(): BehaviorRunner
{
    return new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );
}

function executableBehavior(): array
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
                'type' => 'format_template',
                'template' => 'Student {input.student_name} got {state.result_status}.',
                'assign_to' => 'final_feedback',
            ],
            [
                'type' => 'return',
                'data' => [
                    'status' => '{state.result_status}',
                    'message' => '{state.final_feedback}',
                ],
            ],
        ],
    ];
}

it('executes evaluate_rule and return steps', function () {
    $runner = createBehaviorRunner();

    $result = $runner->run(
        logic: executableBehavior(),
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    );

    expect($result)->toBe([
        'status' => 'pass',
        'message' => 'Student Ahmed got pass.',
    ]);
});

it('assigns the false value when the rule does not match', function () {
    $runner = createBehaviorRunner();

    $result = $runner->run(
        logic: executableBehavior(),
        input: [
            'student_name' => 'Ahmed',
            'score' => 7,
        ],
        context: [],
    );

    expect($result)->toBe([
        'status' => 'fail',
        'message' => 'Student Ahmed got fail.',
    ]);
});

it('resolves values from context', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'format_template',
                'template' => 'Locale: {context.locale}',
                'assign_to' => 'message',
            ],
            [
                'type' => 'return',
                'data' => [
                    'message' => '{state.message}',
                ],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [],
        context: ['locale' => 'ar'],
    ))->toBe([
        'message' => 'Locale: ar',
    ]);
});

it('uses state values produced by previous steps', function () {
    $runner = createBehaviorRunner();

    $logic = [
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
                'assign_to' => 'status',
                'true_value' => 'pass',
                'false_value' => 'fail',
            ],
            [
                'type' => 'format_template',
                'template' => 'Result: {state.status}',
                'assign_to' => 'message',
            ],
            [
                'type' => 'return',
                'data' => [
                    'message' => '{state.message}',
                ],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: ['score' => 12],
        context: [],
    ))->toBe([
        'message' => 'Result: pass',
    ]);
});

it('resolves nested return data recursively', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'format_template',
                'template' => 'Hello {input.student.name}',
                'assign_to' => 'message',
            ],
            [
                'type' => 'return',
                'data' => [
                    'student' => [
                        'name' => '{input.student.name}',
                    ],
                    'result' => [
                        'status' => '{state.message}',
                    ],
                    'score' => 14,
                    'passed' => true,
                    'metadata' => null,
                ],
            ],
        ],
    ];

    expect($runner->run(
        logic: $logic,
        input: [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        context: [],
    ))->toBe([
        'student' => [
            'name' => 'Ahmed',
        ],
        'result' => [
            'status' => 'Hello Ahmed',
        ],
        'score' => 14,
        'passed' => true,
        'metadata' => null,
    ]);
});

it('supports all numeric comparison operators', function ($operator, $expected) {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = $operator;
    $logic['steps'][0]['condition']['value'] = 14;
    $logic['steps'][0]['true_value'] = 'yes';
    $logic['steps'][0]['false_value'] = 'no';

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe($expected);
})->with([
    ['gt', 'no'],
    ['gte', 'yes'],
    ['lt', 'no'],
    ['lte', 'yes'],
]);

it('supports strict equality comparisons', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = 'eq';
    $logic['steps'][0]['condition']['value'] = 14;

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe('pass');
});

it('supports strict inequality comparisons', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = 'neq';
    $logic['steps'][0]['condition']['value'] = 10;

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe('pass');
});

it('propagates unresolved paths', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'format_template',
                'template' => 'Hello {input.student_name}',
                'assign_to' => 'message',
            ],
            [
                'type' => 'return',
                'data' => [
                    'message' => '{state.message}',
                ],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [],
        context: [],
    ))->toThrow(UnresolvablePathException::class);
});

it('rejects non-scalar interpolation values', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'format_template',
                'template' => 'Tags: {input.tags}',
                'assign_to' => 'message',
            ],
            [
                'type' => 'return',
                'data' => [
                    'message' => '{state.message}',
                ],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'tags' => ['math', 'science'],
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Interpolation value for "input.tags" must be scalar.'
    );
});

it('rejects an invalid behavior contract before runtime execution', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = 'gt';
    $logic['steps'][0]['condition']['value'] = '10';

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    ))->toThrow(InvalidBehaviorContractException::class);
});

it('rejects numeric comparison when values are not numeric', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = 'gt';
    $logic['steps'][0]['condition']['value'] = 10;

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => '14',
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Numeric comparison requires numeric values.'
    );
});

it('does not mutate the declared logic', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $original = $logic;

    $runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    );

    expect($logic)->toBe($original);
});

it('rejects behavior execution without a return step', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'format_template',
                'template' => 'Hello {input.student_name}',
                'assign_to' => 'message',
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Behavior execution did not produce a return output.'
    );
});

it('rejects an unsupported behavior step type', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'unsupported_step',
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Unsupported behavior step type "unsupported_step".'
    );
});

it('uses strict type comparison for equality', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();

    $logic['steps'][0]['condition']['operator'] = 'eq';
    $logic['steps'][0]['condition']['value'] = '14';

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe('fail');
});