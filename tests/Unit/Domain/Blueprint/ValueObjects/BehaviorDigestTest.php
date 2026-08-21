<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\BehaviorDigest;

it('accepts a valid sha256 behavior digest', function () {
    $value = 'sha256:' . str_repeat('a', 64);

    $digest = new BehaviorDigest($value);

    expect((string) $digest)->toBe($value);
});

it('rejects an invalid digest prefix', function () {
    new BehaviorDigest('md5:' . str_repeat('a', 64));
})->throws(InvalidArgumentException::class);

it('rejects an invalid digest length', function () {
    new BehaviorDigest('sha256:' . str_repeat('a', 63));
})->throws(InvalidArgumentException::class);

it('rejects uppercase hexadecimal characters', function () {
    new BehaviorDigest('sha256:' . str_repeat('A', 64));
})->throws(InvalidArgumentException::class);

it('compares equal digests correctly', function () {
    $value = 'sha256:' . str_repeat('b', 64);

    $first = new BehaviorDigest($value);
    $second = new BehaviorDigest($value);

    expect($first->equals($second))->toBeTrue();
});

it('compares different digests correctly', function () {
    $first = new BehaviorDigest(
        'sha256:' . str_repeat('a', 64)
    );

    $second = new BehaviorDigest(
        'sha256:' . str_repeat('b', 64)
    );

    expect($first->equals($second))->toBeFalse();
});