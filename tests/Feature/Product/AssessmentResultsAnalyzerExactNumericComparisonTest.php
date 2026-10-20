<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects integer numeric strings whose ordering is lost by float conversion through generic execution', function () {
    $user = User::factory()->create();
    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $blueprint = (new EloquentBlueprintRepository())->findByCanonicalName(
        'skillvlt.edu.assessment',
        'assessment-results-analyzer',
    );

    expect($blueprint)->not->toBeNull();

    $response = $this->actingAs($user)->postJson(
        "/api/blueprints/{$blueprint->id()}/execute",
        [
            'revision_id' => (string) $blueprint->currentRevisionId(),
            'input' => [
                'learners' => [[
                    'name' => 'Precision Boundary',
                    'score' => '9007199254740993',
                    'max_score' => '9007199254740992',
                ]],
            ],
            'context' => [],
        ],
    );

    $response->assertUnprocessable();
    $response->assertJson([
        'message' => 'Input learners.0.score may not exceed max_score.',
    ]);
});

it('compares decimal and scientific numeric strings exactly', function () {
    expect(\App\Application\Execution\NumericValueComparator::compare('1.200', '1.2'))->toBe(0)
        ->and(\App\Application\Execution\NumericValueComparator::compare('1.201', '1.2'))->toBe(1)
        ->and(\App\Application\Execution\NumericValueComparator::compare('1.2e2', '119.99'))->toBe(1)
        ->and(\App\Application\Execution\NumericValueComparator::compare('0.009', '9e-3'))->toBe(0);
});

it('preserves round-trip float precision when comparing mixed numeric values', function () {
    expect(\\App\\Application\\Execution\\NumericValueComparator::compare(
        9007199254740992.0,
        '9007199254740992',
    ))->toBe(0)
        ->and(\\App\\Application\\Execution\\NumericValueComparator::compare(
            9007199254740992.0,
            '9007199254740993',
        ))->toBe(-1)
        ->and(\\App\\Application\\Execution\\NumericValueComparator::compare(
            0.1 + 0.2,
            '0.30000000000000004',
        ))->toBe(0);
});

it('orders numeric values with scientific exponents larger than native integers', function () {
    $lower = '1e-99999999999999999999';
    $higher = '1e-99999999999999999998';

    expect(\App\Application\Execution\NumericValueComparator::compare($lower, $higher))->toBe(-1)
        ->and(\App\Application\Execution\NumericValueComparator::compare($higher, $lower))->toBe(1);
});
