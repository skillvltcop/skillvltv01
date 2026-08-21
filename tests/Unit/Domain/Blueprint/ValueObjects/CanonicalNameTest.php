<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\CanonicalName;

it('accepts a valid canonical name', function () {
    $name = new CanonicalName('assessment-rubric-core');

    expect((string) $name)->toBe('assessment-rubric-core');
});

it('accepts names containing numbers', function () {
    $name = new CanonicalName('assessment-v2');

    expect((string) $name)->toBe('assessment-v2');
});

it('accepts a single word', function () {
    $name = new CanonicalName('assessment');

    expect((string) $name)->toBe('assessment');
});

it('rejects uppercase characters', function () {
    new CanonicalName('Assessment-Rubric');
})->throws(\InvalidArgumentException::class);

it('rejects underscores', function () {
    new CanonicalName('assessment_rubric');
})->throws(\InvalidArgumentException::class);

it('rejects spaces', function () {
    new CanonicalName('assessment rubric');
})->throws(\InvalidArgumentException::class);

it('rejects consecutive hyphens', function () {
    new CanonicalName('assessment--rubric');
})->throws(\InvalidArgumentException::class);

it('rejects a leading hyphen', function () {
    new CanonicalName('-assessment');
})->throws(\InvalidArgumentException::class);

it('rejects a trailing hyphen', function () {
    new CanonicalName('assessment-');
})->throws(\InvalidArgumentException::class);

it('compares equal names correctly', function () {
    $first = new CanonicalName('assessment-rubric');
    $second = new CanonicalName('assessment-rubric');

    expect($first->equals($second))->toBeTrue();
});

it('compares different names correctly', function () {
    $first = new CanonicalName('assessment-rubric');
    $second = new CanonicalName('assessment-mcq');

    expect($first->equals($second))->toBeFalse();
});