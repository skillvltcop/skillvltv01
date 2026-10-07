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

it('calculates assessment score and percentage through a blueprint', function () {
    $user = User::factory()->create();

    $repository = new EloquentBlueprintRepository();

    $blueprint = (new CreateBlueprint($repository))->handle(
        canonicalName: 'assessment-score-calculator',
        namespace: 'skillvlt.edu.assessment',
        ownership: [
            'type' => 'user',
            'id' => (string) $user->id,
        ],
        metadata: [
            'title' => 'Assessment Score Calculator',
            'description' => 'Calculates an assessment score and percentage.',
        ],
    );

    $revision = (new AddBlueprintRevision(
        repository: $repository,
        behaviorContractValidator: new BehaviorContractValidator(),
    ))->handle(
        blueprintId: (string) $blueprint->id(),
        number: '1.0.0',
        contracts: [
            'input' => [
                'type' => 'object',
                'required' => ['correct', 'total'],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'calculate',
                    'operation' => 'divide',
                    'left' => 'input.correct',
                    'right' => 'input.total',
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
                    'type' => 'return',
                    'data' => [
                        'score' => '{input.correct}',
                        'percentage' => '{state.percentage}',
                    ],
                ],
            ],
        ],
        outputs: [
            'type' => 'assessment-score-calculator',
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
        [17, 20, 85],
        [12, 20, 60],
        [0, 20, 0],
        [20, 20, 100],
        [45, 60, 75],
    ];

    foreach ($cases as [$correct, $total, $expectedPercentage]) {
        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/blueprints/{$blueprint->id()}/execute",
                [
                    'revision_id' => (string) $revision->id(),
                    'input' => [
                        'correct' => $correct,
                        'total' => $total,
                    ],
                    'context' => [],
                ],
            );

        $response->assertSuccessful();
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('output.score', $correct);

        expect((float) $response->json('output.percentage'))
            ->toBeGreaterThanOrEqual($expectedPercentage - 0.000001)
            ->toBeLessThanOrEqual($expectedPercentage + 0.000001);
    }
});
