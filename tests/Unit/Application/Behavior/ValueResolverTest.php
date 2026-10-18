<?php

use App\Application\Behavior\ValueResolver;
use App\Domain\Behavior\Exceptions\UnresolvablePathException;

beforeEach(function () {
    $this->resolver = new ValueResolver();
});

test('it resolves values from input', function () {
    expect($this->resolver->resolve(
        'input.score',
        ['score' => 14],
        [],
        [],
    ))->toBe(14);
});

test('it resolves nested values from input', function () {
    expect($this->resolver->resolve(
        'input.student.name',
        [
            'student' => [
                'name' => 'Ahmed',
            ],
        ],
        [],
        [],
    ))->toBe('Ahmed');
});

test('it resolves values from context', function () {
    expect($this->resolver->resolve(
        'context.locale',
        [],
        ['locale' => 'ar'],
        [],
    ))->toBe('ar');
});

test('it resolves values from state', function () {
    expect($this->resolver->resolve(
        'state.result_status',
        [],
        [],
        ['result_status' => 'pass'],
    ))->toBe('pass');
});

test('it resolves an existing null value', function () {
    expect($this->resolver->resolve(
        'state.result',
        [],
        [],
        ['result' => null],
    ))->toBeNull();
});

test('it rejects an invalid root path', function () {
    expect(fn () => $this->resolver->resolve(
        'user.name',
        [],
        [],
        [],
    ))->toThrow(UnresolvablePathException::class);
});

test('it rejects malformed paths', function ($path) {
    expect(fn () => $this->resolver->resolve(
        $path,
        [],
        [],
        [],
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'input.',
    'input..score',
    'state.',
    'context..locale',
    'input.score.foo.',
]);

test('it rejects a missing value', function () {
    expect(fn () => $this->resolver->resolve(
        'input.score',
        [],
        [],
        [],
    ))->toThrow(UnresolvablePathException::class);
});

test('it resolves array values', function () {
    $value = $this->resolver->resolve(
        'input.tags',
        ['tags' => ['math', 'science']],
        [],
        [],
    );

    expect($value)->toBe(['math', 'science']);
});

test('it resolves nested values from the current map item', function () {
    expect($this->resolver->resolve(
        'item.student.name',
        [],
        [],
        [],
        [
            'student' => [
                'name' => 'Sara',
            ],
        ],
    ))->toBe('Sara');
});


test('it resolves numeric indexes in nested array paths', function ($path, $target, $expected) {
    expect($this->resolver->resolve(
        $path,
        $target,
        [],
        [],
    ))->toBe($expected);
})->with([
    'first list item' => [
        'input.learners.0.name',
        ['learners' => [['name' => 'Sara'], ['name' => 'Youssef']]],
        'Sara',
    ],
    'later list item' => [
        'input.learners.1.score',
        ['learners' => [['score' => 12], ['score' => 18]]],
        18,
    ],
    'nested list item' => [
        'input.groups.0.learners.1.name',
        ['groups' => [['learners' => [['name' => 'Ali'], ['name' => 'Mina']]]]],
        'Mina',
    ],
]);

test('it rejects missing numeric indexes in nested array paths', function ($path, $target) {
    expect(fn () => $this->resolver->resolve(
        $path,
        $target,
        [],
        [],
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'out of range index' => [
        'input.learners.2.name',
        ['learners' => [['name' => 'Sara']]],
    ],
    'index into empty list' => [
        'input.learners.0.name',
        ['learners' => []],
    ],
]);


test('it resolves falsy values without treating them as missing', function ($key, $value) {
    expect($this->resolver->resolve(
        "input.{$key}",
        [$key => $value],
        [],
        [],
    ))->toBe($value);
})->with([
    'false' => ['enabled', false],
    'zero integer' => ['count', 0],
    'zero float' => ['ratio', 0.0],
    'empty string' => ['label', ''],
    'empty array' => ['items', []],
]);
