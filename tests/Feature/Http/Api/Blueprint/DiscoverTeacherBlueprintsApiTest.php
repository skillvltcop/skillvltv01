<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('exposes only teacher-facing blueprint information', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();
    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/teacher/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'slug',
                'title',
                'target_level',
                'purpose',
                'version',
            ],
        ],
    ]);

    $response->assertJsonPath('data.0.slug', 'assessment-positioning');
    $response->assertJsonPath('data.0.title', 'Assessment Positioning');
    $response->assertJsonPath('data.0.target_level', '5-6');
    $response->assertJsonPath(
        'data.0.purpose',
        'Determines whether a learner needs support from an assessment score.',
    );
    $response->assertJsonPath('data.0.version', 'v1.1.0');

    $response->assertJsonMissingPath('data.0.current_revision_id');
    $response->assertJsonMissingPath('data.0.namespace');
    $response->assertJsonMissingPath('data.0.lifecycle_status');
    $response->assertJsonMissingPath('data.0.ownership');
    $response->assertJsonMissingPath('data.0.id');
});

it('discovers the assessment score calculator for teachers', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();
    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/teacher/blueprints/discover');

    $response->assertSuccessful();

    $data = $response->json('data');

    $calculator = collect($data)->firstWhere('slug', 'assessment-score-calculator');

    expect($calculator)->not->toBeNull()
        ->and($calculator['title'])->toBe('Assessment Score Calculator')
        ->and($calculator['target_level'])->toBe('5-6')
        ->and($calculator['purpose'])->toBe(
            'Calculates the score and percentage from correct answers and the total.',
        )
        ->and($calculator['version'])->toBe('v1.0.0');
});

it('localizes teacher blueprint discovery from the session locale', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'fr'])
        ->withHeader('Origin', 'http://localhost')
        ->getJson('/api/teacher/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.title', 'Positionnement évaluatif');
    $response->assertJsonPath(
        'data.0.purpose',
        'Déterminer si l’apprenant a besoin d’un soutien à partir de son score.',
    );
    $response->assertJsonPath('data.0.target_level', '5-6');
});

it('rejects unauthenticated teacher blueprint discovery', function () {
    $response = $this->getJson('/api/teacher/blueprints/discover');

    $response->assertUnauthorized();
});
