<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;

function metadataContractBlueprint(): Blueprint
{
    return Blueprint::create(
        canonicalName: new CanonicalName('metadata-contract-test'),
        namespace: new BlueprintNamespace('skillvlt.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'assessment',
            ],
        ],
    );
}

it('rejects unsupported metadata fields on creation', function () {
    expect(fn () => Blueprint::create(
        canonicalName: new CanonicalName('metadata-contract-test'),
        namespace: new BlueprintNamespace('skillvlt.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: [
            'taxonomy' => [
                'domain' => 'assessment',
            ],
            'unsupported' => [
                'value' => true,
            ],
        ],
    ))->toThrow(
        DomainException::class,
        'Unsupported Blueprint metadata field: unsupported.'
    );
});

it('rejects unsupported metadata fields on update', function () {
    $blueprint = metadataContractBlueprint();

    expect(fn () => $blueprint->updateMetadata([
        'taxonomy' => [
            'domain' => 'assessment',
        ],
        'unsupported' => [
            'value' => true,
        ],
    ]))->toThrow(
        DomainException::class,
        'Unsupported Blueprint metadata field: unsupported.'
    );
});
