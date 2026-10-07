<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\RevisionNumber;

it('accepts a valid semantic revision number', function () {
    $revision = new RevisionNumber('1.0.0');

    expect((string) $revision)->toBe('1.0.0');
});

it('accepts major minor and patch values greater than zero', function () {
    $revision = new RevisionNumber('12.34.56');

    expect($revision->major())->toBe(12)
        ->and($revision->minor())->toBe(34)
        ->and($revision->patch())->toBe(56);
});

it('accepts zero values', function () {
    $revision = new RevisionNumber('0.0.0');

    expect($revision->major())->toBe(0)
        ->and($revision->minor())->toBe(0)
        ->and($revision->patch())->toBe(0);
});

it('rejects an incomplete version', function () {
    new RevisionNumber('1.0');
})->throws(\InvalidArgumentException::class);

it('rejects an extended version', function () {
    new RevisionNumber('1.0.0.1');
})->throws(\InvalidArgumentException::class);

it('rejects a version with a v prefix', function () {
    new RevisionNumber('v1.0.0');
})->throws(\InvalidArgumentException::class);

it('rejects leading zeros', function () {
    new RevisionNumber('01.0.0');
})->throws(\InvalidArgumentException::class);

it('rejects non numeric components', function () {
    new RevisionNumber('1.a.0');
})->throws(\InvalidArgumentException::class);

it('compares equal revision numbers correctly', function () {
    $first = new RevisionNumber('1.2.3');
    $second = new RevisionNumber('1.2.3');

    expect($first->equals($second))->toBeTrue();
});

it('compares different revision numbers correctly', function () {
    $first = new RevisionNumber('1.2.3');
    $second = new RevisionNumber('1.2.4');

    expect($first->equals($second))->toBeFalse();
});

it('detects a greater revision number', function () {
    $current = new RevisionNumber('1.2.0');
    $next = new RevisionNumber('1.3.0');

    expect($next->isGreaterThan($current))->toBeTrue();
});

it('compares semantic version components numerically', function () {
    $current = new RevisionNumber('1.2.0');
    $next = new RevisionNumber('1.10.0');

    expect($next->isGreaterThan($current))->toBeTrue();
});

it('compares revision components larger than the PHP integer range correctly', function () {
    $current = new RevisionNumber(
        '999999999999999999999999999999999999.0.0'
    );
    $next = new RevisionNumber(
        '1000000000000000000000000000000000000.0.0'
    );

    expect($next->isGreaterThan($current))->toBeTrue();
    expect($current->isGreaterThan($next))->toBeFalse();
});

it('detects a greater major revision', function () {
    $current = new RevisionNumber('1.9.9');
    $next = new RevisionNumber('2.0.0');

    expect($next->isGreaterThan($current))->toBeTrue();
});

it('rejects equal and lower revision numbers', function () {
    $current = new RevisionNumber('1.2.0');

    expect($current->isGreaterThan(new RevisionNumber('1.2.0')))
        ->toBeFalse();

    expect($current->isGreaterThan(new RevisionNumber('1.1.9')))
        ->toBeTrue();

    expect($current->isGreaterThan(new RevisionNumber('1.3.0')))
        ->toBeFalse();
});