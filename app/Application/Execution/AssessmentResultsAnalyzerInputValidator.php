<?php

declare(strict_types=1);

namespace App\Application\Execution;

use App\Domain\Blueprint\Entities\Blueprint;

final class AssessmentResultsAnalyzerInputValidator
{
    /**
     * @param array<string, mixed> $input
     */
    public function validate(Blueprint $blueprint, array $input): void
    {
        if ((string) $blueprint->canonicalName() !== 'assessment-results-analyzer') {
            return;
        }

        $learners = $input['learners'] ?? null;

        if (! is_array($learners) || $learners === []) {
            throw new \DomainException('Input learners must be a non-empty array.');
        }

        foreach ($learners as $index => $learner) {
            $prefix = "learners.{$index}";

            if (! is_array($learner)) {
                throw new \DomainException("Input {$prefix} must be an object.");
            }

            $name = $learner['name'] ?? null;
            if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 120) {
                throw new \DomainException("Input {$prefix}.name must be a non-empty string of at most 120 characters.");
            }

            $score = $learner['score'] ?? null;
            if (! is_numeric($score) || ! is_finite((float) $score) || (float) $score < 0) {
                throw new \DomainException("Input {$prefix}.score must be a non-negative number.");
            }

            $maxScore = $learner['max_score'] ?? null;
            if (! is_numeric($maxScore) || ! is_finite((float) $maxScore) || (float) $maxScore <= 0) {
                throw new \DomainException("Input {$prefix}.max_score must be greater than zero.");
            }

            if ((float) $score > (float) $maxScore) {
                throw new \DomainException("Input {$prefix}.score may not exceed max_score.");
            }
        }
    }
}
