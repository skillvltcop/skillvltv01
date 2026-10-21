<?php

declare(strict_types=1);

use App\Application\Execution\AssessmentResultsAnalyzerInputValidator;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;

it('does not apply analyzer-specific validation to the same canonical name in another namespace', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-results-analyzer'),
        namespace: new BlueprintNamespace('example.education'),
        ownership: ['type' => 'user', 'id' => 'teacher-1'],
        metadata: [],
    );

    expect(fn () => (new AssessmentResultsAnalyzerInputValidator())->validate(
        $blueprint,
        [],
    ))->not->toThrow(DomainException::class);
});

it('applies analyzer-specific validation within its designated namespace', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-results-analyzer'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: ['type' => 'system', 'id' => 'skillvlt'],
        metadata: [],
    );

    expect(fn () => (new AssessmentResultsAnalyzerInputValidator())->validate(
        $blueprint,
        [],
    ))->toThrow(
        DomainException::class,
        'Input learners must be a non-empty array.'
    );
});

it('rejects positive scores that underflow to zero when cast to float', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-results-analyzer'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: ['type' => 'system', 'id' => 'skillvlt'],
        metadata: [],
    );

    expect(fn () => (new AssessmentResultsAnalyzerInputValidator())->validate(
        $blueprint,
        [
            'learners' => [
                [
                    'name' => 'Tiny Positive Score',
                    'score' => '1e-99999999999999999999',
                    'max_score' => 20,
                ],
            ],
        ],
    ))->toThrow(
        DomainException::class,
        'Input learners.0.score must be a non-negative number.'
    );
});

it('accepts an exact zero score represented with a very small scientific exponent', function () {
    $blueprint = Blueprint::create(
        canonicalName: new CanonicalName('assessment-results-analyzer'),
        namespace: new BlueprintNamespace('skillvlt.edu.assessment'),
        ownership: ['type' => 'system', 'id' => 'skillvlt'],
        metadata: [],
    );

    expect(fn () => (new AssessmentResultsAnalyzerInputValidator())->validate(
        $blueprint,
        [
            'learners' => [
                [
                    'name' => 'Exact Zero Score',
                    'score' => '0e-99999999999999999999',
                    'max_score' => 20,
                ],
            ],
        ],
    ))->not->toThrow(DomainException::class);
});
