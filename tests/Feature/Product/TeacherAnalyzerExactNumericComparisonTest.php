<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects integer numeric strings whose ordering is lost by float conversion through teacher execution', function () {
    $user = User::factory()->create();
    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this->actingAs($user)->postJson(
        '/api/teacher/tools/assessment-results-analyzer/execute',
        [
            'input' => [
                'learners' => [[
                    'name' => 'Precision Boundary',
                    'score' => '9007199254740993',
                    'max_score' => '9007199254740992',
                ]],
            ],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['input.learners.0.score']);
});

it('continues accepting decimal numeric strings through teacher execution', function () {
    $user = User::factory()->create();
    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this->actingAs($user)->postJson(
        '/api/teacher/tools/assessment-results-analyzer/execute',
        [
            'input' => [
                'learners' => [[
                    'name' => 'Decimal Score',
                    'score' => '7.5',
                    'max_score' => '10',
                ]],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
});
