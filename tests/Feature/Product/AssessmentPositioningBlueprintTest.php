<?php

use AppApplicationBehaviorBehaviorContractValidator;
use AppApplicationBlueprintCommandsActivateBlueprint;
use AppApplicationBlueprintCommandsAddBlueprintRevision;
use AppApplicationBlueprintCommandsCreateBlueprint;
use AppApplicationBlueprintCommandsFreezeBlueprintRevision;
use AppApplicationBlueprintCommandsPromoteBlueprintRevision;
use AppInfrastructurePersistenceEloquentEloquentBlueprintRepository;
use AppModelsUser;
use IlluminateFoundationTestingRefreshDatabase;
use TestsTestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('executes the first real assessment positioning blueprint', function () {
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
        number: '1.0.0',
        contracts: [
            'input' => [
                'type' => 'object',
                'required' => ['score'],
            ],
        ],
        logic: [
            'type' => 'steps',
            'version' => 1,
            'steps' => [
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'lt',
                        'value' => 40,
                    ],
                    'assign_to' => 'level',
                    'true_value' => 'priority_support',
                    'false_value' => 'priority_support',
                ],
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gte',
                        'value' => 40,
                    ],
                    'assign_to' => 'level',
                    'true_value' => 'needs_support',
                    'false_value' => 'needs_support',
                ],
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gte',
                        'value' => 60,
                    ],
                    'assign_to' => 'level',
                    'true_value' => 'satisfactory',
                    'false_value' => 'satisfactory',
                ],
                [
                    'type' => 'evaluate_rule',
                    'condition' => [
                        'field' => 'input.score',
                        'operator' => 'gte',
                        'value' => 80,
                    ],
                    'assign_to' => 'level',
                    'true_value' => 'excellent',
                    'false_value' => 'excellent',
                ],
                [
                    'type' => 'return',
                    'data' => [
                        'score' => '{input.score}',
                        'level' => '{state.level}',
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
        40 => 'needs_support',
        59 => 'needs_support',
        60 => 'ready',
        90 => 'ready',
    ];

    foreach ($cases as $score => $expectedPositioning) {
        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/blueprints/{$blueprint->id()}/execute",
                [
                    'revision_id' => (string) $revision->id(),
                    'input' => [
                        'score' => $score,
                    ],
                    'context' => [],
                ],
            );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'status',
            'completed',
        );

        $response->assertJsonPath(
            'output.score',
            (string) $score,
        );

        $response->assertJsonPath(
            'output.positioning',
            $expectedPositioning,
        );
    }
});
