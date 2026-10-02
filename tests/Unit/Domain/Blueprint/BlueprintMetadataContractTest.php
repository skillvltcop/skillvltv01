<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\Enums\LifecycleStatus;

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

it('rejects unsupported metadata fields on reconstitution', function () {
    expect(fn () => Blueprint::reconstitute(
        id: BlueprintId::generate(),
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
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: null,
    ))->toThrow(
        DomainException::class,
        'Unsupported Blueprint metadata field: unsupported.'
    );
});
