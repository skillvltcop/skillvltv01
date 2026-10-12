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

it('rejects malformed learner records through the generic blueprint execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => ['not-a-learner-record'],
            ],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJson([
        'message' => 'Input learners.0 must be an object.',
    ]);
});


it('rejects invalid learner field values through the generic blueprint execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $invalidLearners = [
        [
            'learner' => ['name' => 'Learner', 'score' => -1, 'max_score' => 20],
            'message' => 'Input learners.0.score must be a non-negative number.',
        ],
        [
            'learner' => ['name' => 'Learner', 'score' => 0, 'max_score' => 0],
            'message' => 'Input learners.0.max_score must be greater than zero.',
        ],
        [
            'learner' => ['name' => '   ', 'score' => 1, 'max_score' => 20],
            'message' => 'Input learners.0.name must be a non-empty string of at most 120 characters.',
        ],
        [
            'learner' => ['name' => str_repeat('a', 121), 'score' => 1, 'max_score' => 20],
            'message' => 'Input learners.0.name must be a non-empty string of at most 120 characters.',
        ],
        [
            'learner' => ['score' => 1, 'max_score' => 20],
            'message' => 'Input learners.0.name must be a non-empty string of at most 120 characters.',
        ],
        [
            'learner' => ['name' => 'Learner', 'max_score' => 20],
            'message' => 'Input learners.0.score must be a non-negative number.',
        ],
        [
            'learner' => ['name' => 'Learner', 'score' => 'not-a-number', 'max_score' => 20],
            'message' => 'Input learners.0.score must be a non-negative number.',
        ],
        [
            'learner' => ['name' => 'Learner', 'score' => 1],
            'message' => 'Input learners.0.max_score must be greater than zero.',
        ],
        [
            'learner' => ['name' => 'Learner', 'score' => 1, 'max_score' => 'not-a-number'],
            'message' => 'Input learners.0.max_score must be greater than zero.',
        ],
    ];

    foreach ($invalidLearners as $case) {
        $response = $this->actingAs($user)->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => $revisionId,
                'input' => ['learners' => [$case['learner']]],
                'context' => [],
            ],
        );

        $response->assertUnprocessable();
        $response->assertJson(['message' => $case['message']]);
    }
});

it('accepts zero scores and learner names at the maximum allowed length through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => str_repeat('a', 120), 'score' => 0, 'max_score' => 20],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 1);
    $response->assertJsonPath('output.learners.0.name', str_repeat('a', 120));
    $response->assertJsonPath('output.learners.0.percentage', 0);
});

it('accepts scores equal to the maximum through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Perfect Score', 'score' => 20, 'max_score' => 20],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 1);
    $response->assertJsonPath('output.learners.0.score', 20);
    $response->assertJsonPath('output.learners.0.percentage', 100);
    $response->assertJsonPath('output.learners.0.positioning', 'ready');
});
