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
            'learner' => ['name' => 'Tiny Negative Score', 'score' => '-1e-99999999999999999999', 'max_score' => 20],
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
    $response->assertJsonPath('output.ready_count', 1);
    $response->assertJsonPath('output.needs_support_count', 0);
    $response->assertJsonPath('output.success_rate', 100);
});

it('classifies a learner at the support threshold as ready through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Threshold Learner', 'score' => 12, 'max_score' => 20],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.learners.0.percentage', 60);
    $response->assertJsonPath('output.learners.0.positioning', 'ready');
    $response->assertJsonPath('output.ready_count', 1);
    $response->assertJsonPath('output.needs_support_count', 0);
});

it('classifies a learner below the support threshold as needing support through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Support Learner', 'score' => 59, 'max_score' => 100],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.learners.0.percentage', 59);
    $response->assertJsonPath('output.learners.0.positioning', 'needs_support');
    $response->assertJsonPath('output.ready_count', 0);
    $response->assertJsonPath('output.needs_support_count', 1);
    $response->assertJsonPath('output.success_rate', 0);
});

it('summarizes a mixed cohort of ready and support learners through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Ready Learner', 'score' => 12, 'max_score' => 20],
                    ['name' => 'Support Learner', 'score' => 59, 'max_score' => 100],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 2);
    $response->assertJsonPath('output.learners.0.positioning', 'ready');
    $response->assertJsonPath('output.learners.1.positioning', 'needs_support');
    $response->assertJsonPath('output.average_percentage', 59.5);
    $response->assertJsonPath('output.ready_count', 1);
    $response->assertJsonPath('output.needs_support_count', 1);
    $response->assertJsonPath('output.success_rate', 50);
});

it('validates every learner record and reports the invalid learner index through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Valid Learner', 'score' => 15, 'max_score' => 20],
                    ['name' => 'Invalid Learner', 'score' => 21, 'max_score' => 20],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJson([
        'message' => 'Input learners.1.score may not exceed max_score.',
    ]);
});

it('rejects missing or non-array learner groups through the generic blueprint execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    foreach ([[], ['learners' => 'not-an-array']] as $input) {
        $response = $this->actingAs($user)->postJson(
            "/api/blueprints/{$blueprint->id()}/execute",
            [
                'revision_id' => $revisionId,
                'input' => $input,
                'context' => [],
            ],
        );

        $response->assertUnprocessable();
        $response->assertJson([
            'message' => 'Input learners must be a non-empty array.',
        ]);
    }
});

it('accepts decimal learner scores and calculates their percentages through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Decimal Ready', 'score' => 7.5, 'max_score' => 10],
                    ['name' => 'Decimal Support', 'score' => 2.5, 'max_score' => 10],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 2);
    $response->assertJsonPath('output.learners.0.percentage', 75);
    $response->assertJsonPath('output.learners.0.positioning', 'ready');
    $response->assertJsonPath('output.learners.1.percentage', 25);
    $response->assertJsonPath('output.learners.1.positioning', 'needs_support');
    $response->assertJsonPath('output.average_percentage', 50);
    $response->assertJsonPath('output.ready_count', 1);
    $response->assertJsonPath('output.needs_support_count', 1);
    $response->assertJsonPath('output.success_rate', 50);
});

it('preserves fractional percentages and classifies learners around the support threshold', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Below Threshold', 'score' => 1, 'max_score' => 3],
                    ['name' => 'Above Threshold', 'score' => 2, 'max_score' => 3],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 2);
    $output = $response->json('output');

    expect(abs($output['learners'][0]['percentage'] - (100 / 3)))->toBeLessThan(0.000001)
        ->and($output['learners'][0]['positioning'])->toBe('needs_support')
        ->and(abs($output['learners'][1]['percentage'] - (200 / 3)))->toBeLessThan(0.000001)
        ->and($output['learners'][1]['positioning'])->toBe('ready')
        ->and(abs($output['average_percentage'] - 50))->toBeLessThan(0.000001)
        ->and($output['ready_count'])->toBe(1)
        ->and($output['needs_support_count'])->toBe(1)
        ->and(abs($output['success_rate'] - 50))->toBeLessThan(0.000001);
});

it('accepts numeric-string scores and calculates percentages through the generic execution API', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => $revisionId,
            'input' => [
                'learners' => [
                    ['name' => 'Numeric Strings', 'score' => '7.5', 'max_score' => '10'],
                ],
            ],
            'context' => [],
        ],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('output.count', 1);
    $response->assertJsonPath('output.learners.0.percentage', 75);
    $response->assertJsonPath('output.learners.0.positioning', 'ready');
    $response->assertJsonPath('output.average_percentage', 75);
    $response->assertJsonPath('output.ready_count', 1);
    $response->assertJsonPath('output.needs_support_count', 0);
    $response->assertJsonPath('output.success_rate', 100);
});


it('rejects non-finite analyzer score and maximum values before generic execution', function () {
    $user = User::factory()->create();
    [$blueprint, $revisionId] = analyzerBlueprintForGenericExecution();

    foreach ([
        [
            'learner' => ['name' => 'Infinite Score', 'score' => '1e309', 'max_score' => '1e309'],
            'message' => 'Input learners.0.score must be a non-negative number.',
        ],
        [
            'learner' => ['name' => 'Infinite Maximum', 'score' => 0, 'max_score' => '1e309'],
            'message' => 'Input learners.0.max_score must be greater than zero.',
        ],
    ] as $case) {
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
