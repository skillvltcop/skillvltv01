<?php

use App\Application\Behavior\BehaviorDigestCalculator;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;

function digestLogic(): array
{
    return [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'return',
                'data' => [
                    'status' => 'ok',
                ],
            ],
        ],
    ];
}

it('calculates a sha256 behavior digest from canonical logic', function () {
    $calculator = new BehaviorDigestCalculator();

    $digest = $calculator->calculate(digestLogic());

    expect($digest)
        ->toBeInstanceOf(BehaviorDigest::class)
        ->and((string) $digest)
        ->toBe('sha256:fa86626d8f2b1e31d24d0ebf1c3e9f7efb5cecad73b12624dbf379f04ce120b7');
});

it('is insensitive to associative key order', function () {
    $calculator = new BehaviorDigestCalculator();

    $first = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            [
                'type' => 'return',
                'data' => [
                    'status' => 'ok',
                    'message' => 'done',
                ],
            ],
        ],
    ];

    $second = [
        'version' => 1,
        'steps' => [
            [
                'data' => [
                    'message' => 'done',
                    'status' => 'ok',
                ],
                'type' => 'return',
            ],
        ],
        'type' => 'steps',
    ];

    expect($calculator->calculate($first)->equals(
        $calculator->calculate($second)
    ))->toBeTrue();
});

it('preserves list order as part of the behavior digest', function () {
    $calculator = new BehaviorDigestCalculator();

    $first = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            ['type' => 'format_template', 'template' => 'a', 'assign_to' => 'x'],
            ['type' => 'return', 'data' => ['value' => '{state.x}']],
        ],
    ];

    $second = [
        'type' => 'steps',
        'version' => 1,
        'steps' => [
            ['type' => 'return', 'data' => ['value' => '{state.x}']],
            ['type' => 'format_template', 'template' => 'a', 'assign_to' => 'x'],
        ],
    ];

    expect($calculator->calculate($first)->equals(
        $calculator->calculate($second)
    ))->toBeFalse();
});

it('changes the digest when a behavior value changes', function () {
    $calculator = new BehaviorDigestCalculator();

    $first = digestLogic();
    $second = digestLogic();
    $second['steps'][0]['data']['status'] = 'changed';

    expect($calculator->calculate($first)->equals(
        $calculator->calculate($second)
    ))->toBeFalse();
});
