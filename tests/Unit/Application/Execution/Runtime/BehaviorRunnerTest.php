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
    ))->toThrow(InvalidBehaviorContractException::class);
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
    ))->toThrow(InvalidBehaviorContractException::class);
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

it('treats an integer and numeric string as unequal in a neq condition', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();

    $logic['steps'][0]['condition']['operator'] = 'neq';
    $logic['steps'][0]['condition']['value'] = '14';

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe('pass');
});

it('maps learner results and aggregates assessment group statistics', function () {
    $runner = createBehaviorRunner();

    $result = $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'map',
                    'source' => 'input.learners',
                    'assign_to' => 'results',
                    'steps' => [
                        [
                            'type' => 'calculate',
                            'operation' => 'divide',
                            'left' => 'item.score',
                            'right' => 'item.max_score',
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
                            'type' => 'evaluate_rule',
                            'condition' => [
                                'field' => 'state.percentage',
                                'operator' => 'gte',
                                'value' => 60,
                            ],
                            'assign_to' => 'positioning',
                            'true_value' => 'ready',
                            'false_value' => 'needs_support',
                        ],
                        [
                            'type' => 'return',
                            'data' => [
                                'name' => '{item.name}',
                                'percentage' => '{state.percentage}',
                                'positioning' => '{state.positioning}',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'aggregate',
                    'source' => 'state.results',
                    'operation' => 'count',
                    'assign_to' => 'learner_count',
                ],
                [
                    'type' => 'aggregate',
                    'source' => 'state.results',
                    'operation' => 'average',
                    'field' => 'percentage',
                    'assign_to' => 'average_percentage',
                ],
                [
                    'type' => 'aggregate',
                    'source' => 'state.results',
                    'operation' => 'count_matching',
                    'field' => 'positioning',
                    'operator' => 'eq',
                    'value' => 'ready',
                    'assign_to' => 'ready_count',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'learners' => '{state.results}',
                        'count' => '{state.learner_count}',
                        'average_percentage' => '{state.average_percentage}',
                        'ready_count' => '{state.ready_count}',
                    ],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['name' => 'Ahmed', 'score' => 14, 'max_score' => 20],
                ['name' => 'Sara', 'score' => 18, 'max_score' => 20],
                ['name' => 'Youssef', 'score' => 9, 'max_score' => 20],
                ['name' => 'Mariam', 'score' => 12, 'max_score' => 20],
            ],
        ],
    );

    expect($result['count'])->toBe(4);
    expect($result['average_percentage'])->toBe(66.25);
    expect($result['ready_count'])->toBe(3);
    expect($result['learners'])->toHaveCount(4);
    expect($result['learners'][2]['positioning'])->toBe('needs_support');
});

it('rejects averaging an empty collection', function () {
    $runner = createBehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.items',
                    'operation' => 'average',
                    'field' => 'score',
                    'assign_to' => 'average',
                ],
                [
                    'type' => 'return',
                    'data' => ['average' => '{state.average}'],
                ],
            ],
        ],
        input: ['items' => []],
    ))->toThrow(DomainException::class, 'Cannot average an empty collection.');
});


it('sums a numeric field across an aggregate source', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'sum',
                    'field' => 'score',
                    'assign_to' => 'total_score',
                ],
                [
                    'type' => 'return',
                    'data' => ['total' => '{state.total_score}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 12],
                ['score' => 3],
                ['score' => 5],
            ],
        ],
    ))->toBe(['total' => 20]);
});


it('returns zero when summing an empty aggregate source', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'sum',
                    'field' => 'score',
                    'assign_to' => 'total_score',
                ],
                [
                    'type' => 'return',
                    'data' => ['total' => '{state.total_score}'],
                ],
            ],
        ],
        input: ['learners' => []],
    ))->toBe(['total' => 0]);
});


it('returns zero when counting an empty aggregate source', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count',
                    'assign_to' => 'learner_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.learner_count}'],
                ],
            ],
        ],
        input: ['learners' => []],
    ))->toBe(['count' => 0]);
});

it('returns zero when count_matching has an empty aggregate source', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'positioning',
                    'operator' => 'eq',
                    'value' => 'ready',
                    'assign_to' => 'ready_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.ready_count}'],
                ],
            ],
        ],
        input: ['learners' => []],
    ))->toBe(['count' => 0]);
});

it('counts matching entries in an aggregate source', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'positioning',
                    'operator' => 'eq',
                    'value' => 'ready',
                    'assign_to' => 'ready_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.ready_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['positioning' => 'ready'],
                ['positioning' => 'needs_support'],
                ['positioning' => 'ready'],
            ],
        ],
    ))->toBe(['count' => 2]);
});

it('rejects non-finite values in numeric count_matching comparisons', function () {
    $runner = createBehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'gt',
                    'value' => 100,
                    'assign_to' => 'matching_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.matching_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => INF],
            ],
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Aggregate comparison value must be finite.'
    );
});

it('counts matching entries with a greater-than comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'gt',
                    'value' => 10,
                    'assign_to' => 'high_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.high_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 12],
                ['score' => 15],
                ['score' => 10],
            ],
        ],
    ))->toBe(['count' => 2]);
});

it('counts matching entries with a less-than-or-equal comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'lte',
                    'value' => 10,
                    'assign_to' => 'low_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.low_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 12],
                ['score' => 10],
                ['score' => 4],
            ],
        ],
    ))->toBe(['count' => 3]);
});

it('counts matching entries with a greater-than-or-equal comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'gte',
                    'value' => 10,
                    'assign_to' => 'qualified_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.qualified_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 12],
                ['score' => 10],
                ['score' => 4],
            ],
        ],
    ))->toBe(['count' => 2]);
});

it('counts matching entries with a less-than comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'lt',
                    'value' => 10,
                    'assign_to' => 'low_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.low_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 12],
                ['score' => 10],
                ['score' => 4],
            ],
        ],
    ))->toBe(['count' => 2]);
});

it('counts matching entries with a strict inequality comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'neq',
                    'value' => 10,
                    'assign_to' => 'different_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.different_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 12],
                ['score' => 10],
                ['score' => 4],
            ],
        ],
    ))->toBe(['count' => 3]);
});

it('counts matching entries with an equality comparison', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'eq',
                    'value' => 10,
                    'assign_to' => 'matching_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.matching_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 7],
                ['score' => 10],
                ['score' => 12],
                ['score' => 10],
            ],
        ],
    ))->toBe(['count' => 2]);
});


it('uses strict inequality when counting matching entries', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'neq',
                    'value' => 10,
                    'assign_to' => 'different_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.different_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 10],
                ['score' => '10'],
                ['score' => 9],
            ],
        ],
    ))->toBe(['count' => 2]);
});

it('uses strict equality when counting matching entries', function () {
    $runner = createBehaviorRunner();

    expect($runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'eq',
                    'value' => 10,
                    'assign_to' => 'matching_score_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.matching_score_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 10],
                ['score' => '10'],
                ['score' => 9],
            ],
        ],
    ))->toBe(['count' => 1]);
});

it('uses strict type comparison for inequality', function () {
    $runner = createBehaviorRunner();

    $logic = executableBehavior();
    $logic['steps'][0]['condition']['operator'] = 'neq';
    $logic['steps'][0]['condition']['value'] = '14';

    expect($runner->run(
        logic: $logic,
        input: [
            'student_name' => 'Ahmed',
            'score' => 14,
        ],
        context: [],
    )['status'])->toBe('pass');
});


it('rejects a calculation that overflows to a non-finite result', function () {
    $runner = createBehaviorRunner();
    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'calculate',
                'operation' => 'multiply',
                'left' => 'input.left',
                'right' => 'input.right',
                'assign_to' => 'result',
            ],
            [
                'type' => 'return',
                'data' => ['result' => '{state.result}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'left' => 1.0e308,
            'right' => 1.0e308,
        ],
        context: [],
    ))->toThrow(DomainException::class, 'Calculation result must be finite.');
});

it('rejects non-finite aggregate sum results', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'aggregate',
                'operation' => 'sum',
                'source' => 'input.values',
                'field' => 'amount',
                'assign_to' => 'total',
            ],
            [
                'type' => 'return',
                'data' => ['total' => '{state.total}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'values' => [
                ['amount' => 1.0e308],
                ['amount' => 1.0e308],
            ],
        ],
    ))->toThrow(
        DomainException::class,
        'Aggregate result must be finite.'
    );
});


it('rejects a non-finite calculation result inside a map step', function () {
    $runner = createBehaviorRunner();

    $logic = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'map',
                'source' => 'input.learners',
                'assign_to' => 'results',
                'steps' => [
                    [
                        'type' => 'calculate',
                        'operation' => 'multiply',
                        'left' => 'item.score',
                        'right' => 'item.multiplier',
                        'assign_to' => 'result',
                    ],
                    [
                        'type' => 'return',
                        'data' => ['result' => '{state.result}'],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => ['results' => '{state.results}'],
            ],
        ],
    ];

    expect(fn () => $runner->run(
        logic: $logic,
        input: [
            'learners' => [
                ['score' => 1.0e308, 'multiplier' => 1.0e308],
            ],
        ],
        context: [],
    ))->toThrow(DomainException::class, 'Calculation result must be finite.');
});


it('rejects a non-finite average aggregate result', function () {
    $runner = createBehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'average',
                    'field' => 'score',
                    'assign_to' => 'average_score',
                ],
                [
                    'type' => 'return',
                    'data' => ['average' => '{state.average_score}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 1.0e308],
                ['score' => 1.0e308],
            ],
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Aggregate result must be finite.'
    );
});


it('rejects NAN values in numeric count_matching comparisons', function () {
    $runner = createBehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'gt',
                    'value' => 100,
                    'assign_to' => 'matching_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.matching_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => NAN],
            ],
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Aggregate comparison value must be finite.'
    );
});

it('rejects non-finite thresholds in numeric count_matching comparisons', function () {
    $runner = createBehaviorRunner();

    expect(fn () => $runner->run(
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'aggregate',
                    'source' => 'input.learners',
                    'operation' => 'count_matching',
                    'field' => 'score',
                    'operator' => 'gt',
                    'value' => NAN,
                    'assign_to' => 'matching_count',
                ],
                [
                    'type' => 'return',
                    'data' => ['count' => '{state.matching_count}'],
                ],
            ],
        ],
        input: [
            'learners' => [
                ['score' => 101],
            ],
        ],
        context: [],
    ))->toThrow(
        DomainException::class,
        'Aggregate comparison value must be finite.'
    );
});
