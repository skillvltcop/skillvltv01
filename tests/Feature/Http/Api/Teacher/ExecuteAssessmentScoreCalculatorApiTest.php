<?php

use App\Models\User;
use Database\Seeders\AssessmentScoreCalculatorBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('returns the score and percentage consumed by the assessment score calculator page', function () {
    $user = User::factory()->create();

    (new AssessmentScoreCalculatorBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/teacher/tools/assessment-score-calculator/execute', [
            'input' => [
                'correct' => 7,
                'total' => 10,
            ],
            'context' => [],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('result.score', 7);
    $response->assertJsonPath('result.percentage', 70);
    $response->assertJsonPath('error', null);
    $response->assertJsonMissingPath('output');
    $response->assertJsonMissingPath('revision_id');
});
