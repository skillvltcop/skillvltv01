<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

it('accepts a valid map step contract', function () {
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

    expect(fn () => (new BehaviorContractValidator())->validate($logic))
        ->not->toThrow(InvalidBehaviorContractException::class);
});
