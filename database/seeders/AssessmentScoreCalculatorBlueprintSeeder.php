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

final class AssessmentScoreCalculatorBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $repository = new EloquentBlueprintRepository();

        $existing = $repository->findByCanonicalName(
            'skillvlt.edu.assessment',
            'assessment-score-calculator',
        );

        $metadata = [
            'title' => [
                'ar' => 'حاسبة نتيجة التقويم',
                'fr' => 'Calculateur de score d’évaluation',
                'en' => 'Assessment Score Calculator',
            ],
            'description' => [
                'ar' => 'حساب نتيجة التقويم ونسبتها المئوية.',
                'fr' => 'Calculer le score d’évaluation et son pourcentage.',
                'en' => 'Calculates an assessment score and percentage.',
            ],
            'target_level' => [
                'ar' => '5-6',
                'fr' => '5-6',
                'en' => '5-6',
            ],
            'purpose' => [
                'ar' => 'حساب النتيجة والنسبة المئوية انطلاقًا من عدد الإجابات الصحيحة والمجموع.',
                'fr' => 'Calculer le score et le pourcentage à partir des réponses correctes et du total.',
                'en' => 'Calculates the score and percentage from correct answers and the total.',
            ],
        ];

        if ($existing !== null) {
            $existing->updateMetadata($metadata);
            $repository->save($existing);

            if ($existing->revisions() === []) {
                $revision = $this->addRevision($repository, $existing);

                (new FreezeBlueprintRevision($repository))->handle(
                    blueprintId: (string) $existing->id(),
                    revisionId: (string) $revision->id(),
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
            canonicalName: 'assessment-score-calculator',
            namespace: 'skillvlt.edu.assessment',
            ownership: [
                'type' => 'system',
                'id' => 'skillvlt',
            ],
            metadata: $metadata,
        );

        $revision = $this->addRevision($repository, $blueprint);

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

    private function addRevision(
        EloquentBlueprintRepository $repository,
        mixed $blueprint,
    ): mixed {
        return (new AddBlueprintRevision(
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
                'visibility' => 'public',
            ],
        );
    }
}
