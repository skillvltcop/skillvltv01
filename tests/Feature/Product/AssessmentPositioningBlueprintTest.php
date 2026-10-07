<?php

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes the assessment positioning blueprint v1.1 using score and max score', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-positioning',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [
            'title' => 'Assessment Positioning',
            'description' => 'Determines whether a learner needs support from an assessment score.',
        ],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.1.0',
        contracts: [
            'input' => [
                'type' => 'object',
                'required' => ['score', 'max_score'],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'calculate',
                    'operation' => 'divide',
                    'left' => 'input.score',
                    'right' => 'input.max_score',
                    'assign_to' => 'ratio',
                ],
                [
                    'type' => 'calculate',
                    'operation' => 'multiply',
                    'left' => 'state.ratio',
                    'right' => 100,
                    'assign_to' => 'percentage',
                ],
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'state.percentage',
                        'operator' => 'gte',
                        'value' => 60,
                    ],
                    'assign_to' => 'positioning',
                    'true_value' => 'ready',
                    'false_value' => 'needs_support',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'score' => '{input.score}',
                        'max_score' => '{input.max_score}',
                        'percentage' => '{state.percentage}',
                        'positioning' => '{state.positioning}',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'assessment-positioning',
            'schema_version' => 1,
        ],
        policies: [
            'visibility' => 'private',
        ],
    );

    (new FreezeBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new PromoteBlueprintRevision($repository))->handle(
        blueprintId: (string) $blueprint->id(),
        revisionId: (string) $revision->id(),
    );

    (new ActivateBlueprint($repository))->handle(
        blueprintId: (string) $blueprint->id(),
    );

    $cases = [
        [14, 20, 70, 'ready'],
        [12, 20, 60, 'ready'],
        [11, 20, 55, 'needs_support'],
        [45, 60, 75, 'ready'],
        [0, 20, 0, 'needs_support'],
        [20, 20, 100, 'ready'],
    ];

    foreach ($cases as [$score, $maxScore, $expectedPercentage, $expectedPositioning]) {
        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/blueprints/{$blueprint->id()}/execute",
                [
                    'revision_id' => (string) $revision->id(),
                    'input' => [
                        'score' => $score,
                        'max_score' => $maxScore,
                    ],
                    'context' => [],
                ],
            );

        $response->assertSuccessful();

        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('output.score', $score);
        $response->assertJsonPath('output.max_score', $maxScore);
        expect((float) $response->json('output.percentage'))
            ->toBeGreaterThanOrEqual($expectedPercentage - 0.000001)
            ->toBeLessThanOrEqual($expectedPercentage + 0.000001);
        $response->assertJsonPath('output.positioning', $expectedPositioning);
    }
});
