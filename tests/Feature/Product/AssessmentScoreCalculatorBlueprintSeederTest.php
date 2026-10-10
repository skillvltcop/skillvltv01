<?php

use App\Domain\Blueprint\Enums\LifecycleStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('seeds the assessment score calculator as an active system blueprint', function () {
    $seeder = new AssessmentScoreCalculatorBlueprintSeeder();

    $seeder->run();

    $blueprints = (new EloquentBlueprintRepository())->discover();

    expect($blueprints)->toHaveCount(1);

    $blueprint = $blueprints[0];

    expect((string) $blueprint->canonicalName())
        ->toBe('assessment-score-calculator');

    expect($blueprint->lifecycleStatus())
        ->toBe(LifecycleStatus::ACTIVE);

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

it('does not duplicate the assessment score calculator when seeded twice', function () {
    $seeder = new AssessmentScoreCalculatorBlueprintSeeder();

    $seeder->run();
    $seeder->run();

    expect((new EloquentBlueprintRepository())->discover())
        ->toHaveCount(1);
});

it('promotes the seeded revision when an existing draft has no current revision', function () {
    $seeder = new AssessmentScoreCalculatorBlueprintSeeder();
    $seeder->run();

    $repository = new EloquentBlueprintRepository();
    $blueprint = $repository->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-score-calculator',
    );

    DB::table('blueprints')
        ->where('id', (string) $blueprint->id())
        ->update([
            'current_revision_id' => null,
            'lifecycle_status' => 'draft',
        ]);

    $seeder->run();

    $reloaded = $repository->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-score-calculator',
    );

    expect($reloaded->lifecycleStatus())->toBe(LifecycleStatus::ACTIVE)
        ->and((string) $reloaded->currentRevision()->number())->toBe('1.0.0')
        ->and($reloaded->revisions())->toHaveCount(1);
});
