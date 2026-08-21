<?php

declare(strict_types=1);

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Support\Str;

it('accepts a valid ulid', function () {
    $value = (string) Str::ulid();

    $id = new BlueprintId($value);

    expect((string) $id)->toBe($value);
});

it('generates a valid blueprint id', function () {
    $id = BlueprintId::generate();

    expect($id)->toBeInstanceOf(BlueprintId::class)
        ->and(Str::isUlid((string) $id))->toBeTrue();
});

it('rejects an invalid ulid', function () {
    new BlueprintId('not-a-valid-ulid');
})->throws(InvalidArgumentException::class);

it('compares equal ids correctly', function () {
    $value = (string) Str::ulid();

    $first = new BlueprintId($value);
    $second = new BlueprintId($value);

    expect($first->equals($second))->toBeTrue();
});

it('compares different ids correctly', function () {
    $first = BlueprintId::generate();
    $second = BlueprintId::generate();

    expect($first->equals($second))->toBeFalse();
});