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
            'category' => 'education',
            'visibility' => 'public',
        ],
    );
}

it('accepts arbitrary metadata fields on creation', function () {
    $blueprint = metadataContractBlueprint();

    expect($blueprint->metadata())
        ->toBe([
            'category' => 'education',
            'visibility' => 'public',
        ]);
});

it('accepts arbitrary metadata fields on update', function () {
    $blueprint = metadataContractBlueprint();

    $blueprint->updateMetadata([
        'taxonomy' => [
            'domain' => 'assessment',
        ],
        'category' => 'education',
        'visibility' => 'public',
    ]);

    expect($blueprint->metadata())
        ->toBe([
            'taxonomy' => [
                'domain' => 'assessment',
            ],
            'category' => 'education',
            'visibility' => 'public',
        ]);
});

it('preserves arbitrary metadata fields on reconstitution', function () {
    $metadata = [
        'taxonomy' => [
            'domain' => 'assessment',
        ],
        'category' => 'education',
        'visibility' => 'public',
    ];

    $blueprint = Blueprint::reconstitute(
        id: BlueprintId::generate(),
        canonicalName: new CanonicalName('metadata-contract-test'),
        namespace: new BlueprintNamespace('skillvlt.test'),
        ownership: [
            'type' => 'system',
            'id' => 'skillvlt',
        ],
        metadata: $metadata,
        lifecycleStatus: LifecycleStatus::DRAFT,
        currentRevisionId: null,
    );

    expect($blueprint->metadata())
        ->toBe($metadata);
});
