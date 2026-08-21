<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;

it('accepts a valid namespace', function () {
    $namespace = new BlueprintNamespace('skillvlt.edu.assessment');

    expect((string) $namespace)->toBe('skillvlt.edu.assessment');
});

it('accepts a single namespace segment', function () {
    $namespace = new BlueprintNamespace('skillvlt');

    expect((string) $namespace)->toBe('skillvlt');
});

it('accepts multiple namespace segments', function () {
    $namespace = new BlueprintNamespace('skillvlt.edu.assessment.primary');

    expect((string) $namespace)->toBe('skillvlt.edu.assessment.primary');
});

it('rejects uppercase characters', function () {
    new BlueprintNamespace('SkillVLT.edu');
})->throws(\InvalidArgumentException::class);

it('rejects underscores', function () {
    new BlueprintNamespace('skillvlt_edu');
})->throws(\InvalidArgumentException::class);

it('rejects spaces', function () {
    new BlueprintNamespace('skillvlt edu');
})->throws(\InvalidArgumentException::class);

it('rejects consecutive dots', function () {
    new BlueprintNamespace('skillvlt..assessment');
})->throws(\InvalidArgumentException::class);

it('rejects a leading dot', function () {
    new BlueprintNamespace('.skillvlt');
})->throws(\InvalidArgumentException::class);

it('rejects a trailing dot', function () {
    new BlueprintNamespace('skillvlt.');
})->throws(\InvalidArgumentException::class);

it('compares equal namespaces correctly', function () {
    $first = new BlueprintNamespace('skillvlt.edu.assessment');
    $second = new BlueprintNamespace('skillvlt.edu.assessment');

    expect($first->equals($second))->toBeTrue();
});

it('compares different namespaces correctly', function () {
    $first = new BlueprintNamespace('skillvlt.edu.assessment');
    $second = new BlueprintNamespace('skillvlt.edu.certification');

    expect($first->equals($second))->toBeFalse();
});