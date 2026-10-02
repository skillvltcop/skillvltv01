<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;

it('rejects unsupported metadata fields', function () {
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
