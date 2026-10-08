<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Blueprint\Commands\ActivateBlueprint;
use App\Application\Blueprint\Commands\AddBlueprintRevision;
use App\Application\Blueprint\Commands\CreateBlueprint;
use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use Illuminate\Database\Seeder;

final class AssessmentPositioningBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $repository = new EloquentBlueprintRepository();

        $existing = $repository->findByCanonicalName(
            'skillvlt.edu.assessment',
            'assessment-positioning',
        );

        $metadata = [
                'title' => [
                    'ar' => 'التموضع التقويمي',
                    'fr' => 'Positionnement évaluatif',
                    'en' => 'Assessment Positioning',
                ],
                'description' => [
                    'ar' => 'تحديد ما إذا كان المتعلم يحتاج إلى دعم بناءً على نقطة التقويم.',
                    'fr' => 'Déterminer si l’apprenant a besoin d’un soutien à partir de son score.',
                    'en' => 'Determines whether a learner needs support from an assessment score.',
                ],
                'target_level' => [
                    'ar' => '5-6',
                    'fr' => '5-6',
                    'en' => '5-6',
                ],
                'purpose' => [
                    'ar' => 'تحديد ما إذا كان المتعلم يحتاج إلى دعم بناءً على نقطة التقويم.',
                    'fr' => 'Déterminer si l’apprenant a besoin d’un soutien à partir de son score.',
                    'en' => 'Determines whether a learner needs support from an assessment score.',
                ],
            ];

        if ($existing !== null) {
            $existing->updateMetadata($metadata);
            $repository->save($existing);

            $hasCurrentV11 = $existing->revisions() !== []
                && collect($existing->revisions())->contains(
                    fn ($revision): bool => (string) $revision->number() === '1.1.0'
                );

            if (! $hasCurrentV11) {
                $revision = (new AddBlueprintRevision(
                    repository: $repository,
                    behaviorContractValidator: new BehaviorContractValidator(),
                ))->handle(
                    blueprintId: (string) $existing->id(),
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
                        'visibility' => 'public',
                    ],
                );

                (new FreezeBlueprintRevision($repository))->handle(
                    blueprintId: (string) $existing->id(),
                    revisionId: (string) $revision->id(),
                );

                (new PromoteBlueprintRevision($repository))->handle(
                    blueprintId: (string) $existing->id(),
                    revisionId: (string) $revision->id(),
                );
            }

            if ($hasCurrentV11 && $existing->currentRevision()?->number() !== null
                && (string) $existing->currentRevision()->number() !== '1.1.0') {
                $revision = collect($existing->revisions())->first(
                    fn ($revision): bool => (string) $revision->number() === '1.1.0'
                );

                (new PromoteBlueprintRevision($repository))->handle(
                    blueprintId: (string) $existing->id(),
                    revisionId: (string) $revision->id(),
                );
            }

            if ($existing->lifecycleStatus()->value === 'draft') {
                (new ActivateBlueprint($repository))->handle(
                    blueprintId: (string) $existing->id(),
                );
            }

            return;
        }

        $blueprint = (new CreateBlueprint($repository))->handle(
            canonicalName: 'assessment-positioning',
            namespace: 'skillvlt.edu.assessment',
            ownership: [
                'type' => 'system',
                'id' => 'skillvlt',
            ],
            metadata: $metadata,
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
                'visibility' => 'public',
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
    }
}
