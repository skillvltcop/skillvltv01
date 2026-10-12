<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function analyzerBlueprintForGenericExecution(): array
{
    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $blueprint = (new EloquentBlueprintRepository())->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-results-analyzer',
    );

    expect($blueprint)->not->toBeNull();

    return [$blueprint, (string) $blueprint->currentRevisionId()];
}

it('rejects invalid analyzer scores through the generic blueprint execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Learner', 'score' => 21, 'max_score' => 20],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJson([
        'message' => 'Input learners.0.score may not exceed max_score.',
    ]);
});

it('rejects empty analyzer learner groups through the generic blueprint execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => ['learners' => []],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJson([
        'message' => 'Input learners must be a non-empty array.',
    ]);
});
