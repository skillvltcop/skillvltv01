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

it('returns an empty execution trace when no steps are declared', function () {
    $runner = new BehaviorRunner();

    $result = $runner->run(
        logic: [],
        input: [],
        context: [],
    );

    expect($result)
        ->toBe([
            'steps' => [],
        ]);
});