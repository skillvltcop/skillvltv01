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