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

test('it rejects paths traversing null intermediate values', function ($path, $input, $context, $state, $item) {
    expect(fn () => $this->resolver->resolve(
        $path,
        $input,
        $context,
        $state,
        $item,
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'input null parent' => [
        'input.student.name',
        ['student' => null],
        [],
        [],
        null,
    ],
    'context null parent' => [
        'context.profile.name',
        [],
        ['profile' => null],
        [],
        null,
    ],
    'state null parent' => [
        'state.result.status',
        [],
        [],
        ['result' => null],
        null,
    ],
    'item null parent' => [
        'item.student.name',
        [],
        [],
        [],
        ['student' => null],
    ],
]);

test('it resolves nested paths instead of treating dotted keys as literal keys', function ($target, $expected) {
    expect($this->resolver->resolve(
        'input.profile.name',
        $target,
        [],
        [],
    ))->toBe($expected);
})->with([
    'nested structure takes precedence over dotted key' => [
        [
            'profile' => ['name' => 'Nested'],
            'profile.name' => 'Literal',
        ],
        'Nested',
    ],
    'nested structure resolves when dotted key also exists' => [
        [
            'profile' => ['name' => 'Correct'],
            'profile.name' => 'Wrong',
        ],
        'Correct',
    ],
]);

test('it resolves numeric-looking array keys using path segments', function ($path, $target, $expected) {
    expect($this->resolver->resolve(
        $path,
        $target,
        [],
        [],
    ))->toBe($expected);
})->with([
    'zero-padded numeric key' => [
        'input.codes.01',
        ['codes' => ['01' => 'leading-zero', 1 => 'integer-one']],
        'leading-zero',
    ],
    'numeric key after a nested segment' => [
        'input.codes.10.label',
        ['codes' => ['10' => ['label' => 'ten']]],
        'ten',
    ],
]);

test('it rejects unsupported path syntax', function ($path) {
    expect(fn () => $this->resolver->resolve(
        $path,
        ['tags' => ['math'], 'student-name' => 'Sara', 'student name' => 'Mina'],
        [],
        [],
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'wildcard segment' => 'input.tags.*',
    'bracket index syntax' => 'input.tags[0]',
    'hyphen in key' => 'input.student-name',
    'space in key' => 'input.student name',
    'special character in key' => 'input.$name',
]);


test('it rejects paths traversing scalar intermediate values', function ($path, $input, $context, $state, $item) {
    expect(fn () => $this->resolver->resolve(
        $path,
        $input,
        $context,
        $state,
        $item,
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'input string parent' => [
        'input.student.name',
        ['student' => 'Sara'],
        [],
        [],
        null,
    ],
    'context integer parent' => [
        'context.profile.name',
        [],
        ['profile' => 42],
        [],
        null,
    ],
    'state boolean parent' => [
        'state.result.status',
        [],
        [],
        ['result' => false],
        null,
    ],
    'item float parent' => [
        'item.student.name',
        [],
        [],
        [],
        ['student' => 3.14],
    ],
]);


test('it rejects paths with missing intermediate values', function ($path, $input, $context, $state, $item) {
    expect(fn () => $this->resolver->resolve(
        $path,
        $input,
        $context,
        $state,
        $item,
    ))->toThrow(UnresolvablePathException::class);
})->with([
    'input missing parent' => [
        'input.student.name',
        [],
        [],
        [],
        null,
    ],
    'context missing parent' => [
        'context.profile.name',
        [],
        [],
        [],
        null,
    ],
    'state missing parent' => [
        'state.result.status',
        [],
        [],
        [],
        null,
    ],
    'item missing parent' => [
        'item.student.name',
        [],
        [],
        [],
        [],
    ],
]);
