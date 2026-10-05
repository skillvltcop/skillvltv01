<?php

use App\Domain\Blueprint\Enums\BlueprintLifecycleStatus;
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
        ->toBe(BlueprintLifecycleStatus::ACTIVE);

    expect($blueprint->revisions())
        ->toHaveCount(1);

    $revision = array_values($blueprint->revisions())[0];

    expect((string) $revision->number())
        ->toBe('1.0.0');

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
