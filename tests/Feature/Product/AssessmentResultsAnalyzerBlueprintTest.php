<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('publishes the assessment results analyzer as a discoverable system tool', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/teacher/blueprints/discover?locale=ar');

    $response->assertSuccessful();

    $response->assertJsonPath(
        'data.0.slug',
        'assessment-results-analyzer',
    );

    $response->assertJsonPath(
        'data.0.title',
        'محلل نتائج التقويم',
    );

    $response->assertJsonPath('data.0.target_level', '5-6');
});
