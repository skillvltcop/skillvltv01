<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\RevisionId;
use Illuminate\Support\Str;

it('accepts a valid ulid', function () {
    $value = (string) Str::ulid();

    $id = new RevisionId($value);

    expect((string) $id)->toBe($value);
});

it('generates a valid revision id', function () {
    $id = RevisionId::generate();

    expect($id)->toBeInstanceOf(RevisionId::class)
        ->and(Str::isUlid((string) $id))->toBeTrue();
});

it('rejects an invalid ulid', function () {
    new RevisionId('not-a-valid-ulid');
})->throws(\InvalidArgumentException::class);

it('compares equal ids correctly', function () {
    $value = (string) Str::ulid();

    $first = new RevisionId($value);
    $second = new RevisionId($value);

    expect($first->equals($second))->toBeTrue();
});

it('compares different ids correctly', function () {
    $first = RevisionId::generate();
    $second = RevisionId::generate();

    expect($first->equals($second))->toBeFalse();
});