<?php

use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('seeds the assessment positioning blueprint as an active system blueprint', function () {
    $seeder = new AssessmentPositioningBlueprintSeeder();

    $seeder->run();

    $blueprints = (new EloquentBlueprintRepository())->discover();

    expect($blueprints)->toHaveCount(1);

    $blueprint = $blueprints[0];

    expect((string) $blueprint->canonicalName())
        ->toBe('assessment-positioning');

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::ACTIVE);

    expect($blueprint->revisions())
        ->toHaveCount(1);

    $revision = array_values($blueprint->revisions())[0];

    expect((string) $revision->number())
        ->toBe('1.1.0');

    expect($revision->isFrozen())
        ->toBeTrue();

    expect((string) $blueprint->currentRevisionId())
        ->toBe((string) $revision->id());
});

it('does not duplicate the assessment positioning blueprint when seeded twice', function () {
    $seeder = new AssessmentPositioningBlueprintSeeder();

    $seeder->run();
    $seeder->run();

    expect((new EloquentBlueprintRepository())->discover())
        ->toHaveCount(1);
});

it('does not promote an older seeded revision over a newer current revision', function () {
    $seeder = new AssessmentPositioningBlueprintSeeder();
    $seeder->run();

    $repository = new EloquentBlueprintRepository();
    $blueprint = $repository->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-positioning',
    );

    expect($blueprint)->not->toBeNull();

    $newerRevision = $blueprint->addRevision(
        number: new RevisionNumber('2.0.0'),
        behaviorDigest: new BehaviorDigest('sha256:' . str_repeat('b', 64)),
        contracts: [],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'return',
                    'data' => ['result' => 'updated'],
                ],
            ],
        ],
        outputs: [],
        policies: ['visibility' => 'public'],
    );

    $blueprint->freezeRevision($newerRevision->id());
    $blueprint->promoteRevision($newerRevision->id());
    $repository->save($blueprint);

    $seeder->run();

    $reloaded = $repository->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-positioning',
    );

    expect((string) $reloaded->currentRevision()->number())->toBe('2.0.0')
        ->and($reloaded->revisions())->toHaveCount(2);
});

