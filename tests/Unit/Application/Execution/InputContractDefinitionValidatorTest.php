<?php

use App\\Application\\Execution\\InputContractDefinitionValidator;

it('accepts a list of unique non-empty required field names', function () {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => [
            'type' => 'object',
            'required' => ['score', 'max_score'],
        ],
    ]))->not->toThrow(DomainException::class);
});

it('allows contracts without an input required declaration for backward compatibility', function () {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => ['type' => 'object'],
    ]))->not->toThrow(DomainException::class);

    expect(fn () => $validator->validate([]))
        ->not->toThrow(DomainException::class);
});

it('rejects a non-array input contract', function (mixed $inputContract) {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => $inputContract,
    ]))->toThrow(
        DomainException::class,
        'Input contract must be an array.',
    );
})->with([
    'null' => [null],
    'string' => ['invalid'],
    'integer' => [123],
]);

it('rejects a non-array required declaration', function () {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => ['required' => 'score'],
    ]))->toThrow(
        DomainException::class,
        'Input contract required must be a list of non-empty strings.',
    );
});

it('rejects an associative required declaration instead of a list', function () {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => ['required' => ['primary' => 'score']],
    ]))->toThrow(
        DomainException::class,
        'Input contract required must be a list of non-empty strings.',
    );
});

it('rejects non-string and empty required field names', function (mixed $field) {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => ['required' => [$field]],
    ]))->toThrow(
        DomainException::class,
        'Input contract required must contain only non-empty strings.',
    );
})->with([
    'integer' => [123],
    'null' => [null],
    'empty string' => [''],
    'whitespace only' => ['   '],
]);

it('rejects duplicate required field names', function () {
    $validator = new InputContractDefinitionValidator();

    expect(fn () => $validator->validate([
        'input' => ['required' => ['score', 'score']],
    ]))->toThrow(
        DomainException::class,
        'Input contract required contains duplicate field "score".',
    );
});
