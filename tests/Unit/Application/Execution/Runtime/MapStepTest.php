<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\Runtime\BehaviorRunner;

function mapStepRunner(): BehaviorRunner
{
    return new BehaviorRunner(
        new ValueResolver(),
        new BehaviorContractValidator(),
    );
}

it('maps a nested calculation over a collection', function () {
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
                        'operation' => 'divide',
                        'left' => 'item.score',
                        'right' => 'item.max_score',
                        'assign_to' => 'percentage',
                    ],
                    [
                        'type' => 'calculate',
                        'operation' => 'multiply',
                        'left' => 'state.percentage',
                        'right' => 100,
                        'assign_to' => 'percentage',
                    ],
                    [
                        'type' => 'return',
                        'data' => [
                            'name' => '{item.name}',
                            'percentage' => '{state.percentage}',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'return',
                'data' => [
                    'results' => '{state.results}',
                ],
            ],
        ],
    ];

    $result = mapStepRunner()->run(
        logic: $logic,
        input: [
            'learners' => [
                ['name' => 'أحمد', 'score' => 14, 'max_score' => 20],
                ['name' => 'سارة', 'score' => 18, 'max_score' => 20],
            ],
        ],
    );

    expect($result['results'])->toBe([
        ['name' => 'أحمد', 'percentage' => '70'],
        ['name' => 'سارة', 'percentage' => '90'],
    ]);
});
