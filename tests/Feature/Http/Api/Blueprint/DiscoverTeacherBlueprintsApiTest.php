<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('exposes only teacher-facing blueprint information', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/teacher/blueprints/discover');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'title',
                'target_level',
                'purpose',
                'version',
            ],
        ],
    ]);

    $response->assertJsonPath('data.0.title', 'Assessment Positioning');
    $response->assertJsonPath('data.0.target_level', '5-6');
    $response->assertJsonPath(
        'data.0.purpose',
        'Determines whether a learner needs support from an assessment score.',
    );
    $response->assertJsonPath('data.0.version', 'v1.0.0');

    $response->assertJsonMissingPath('data.0.current_revision_id');
    $response->assertJsonMissingPath('data.0.namespace');
    $response->assertJsonMissingPath('data.0.lifecycle_status');
    $response->assertJsonMissingPath('data.0.ownership');
    $response->assertJsonMissingPath('data.0.id');
});

it('rejects unauthenticated teacher blueprint discovery', function () {
    $response = $this->getJson('/api/teacher/blueprints/discover');

    $response->assertUnauthorized();
});
