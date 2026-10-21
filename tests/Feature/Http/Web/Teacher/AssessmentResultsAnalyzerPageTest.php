<?php

use App\Models\User;
use Database\Seeders\AssessmentResultsAnalyzerBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('renders the assessment results analyzer teacher page through the generic tool route', function () {
    $user = User::factory()->create();

    (new AssessmentResultsAnalyzerBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get('/teacher/tools/assessment-results-analyzer');

    $response->assertSuccessful();
    $response->assertSee('Assessment Results Analyzer');
    $response->assertSee('/api/teacher/tools/assessment-results-analyzer/execute', false);
    $response->assertSee('response.json().catch(() => ({}))', false);
    $response->assertSee('Analyze results');
    $response->assertSee('Maximum score');
    $response->assertSee('Average percentage');
    $response->assertSee('Need support');
});

it('renders the localized shell for supported locales', function () {
    $user = User::factory()->create();

    $expected = [
        'ar' => [
            'title' => 'محلل نتائج التقويم',
            'execute' => 'تحليل النتائج',
        ],
        'fr' => [
            'title' => 'Analyseur des résultats d’évaluation',
            'execute' => 'Analyser les résultats',
        ],
        'en' => [
            'title' => 'Assessment Results Analyzer',
            'execute' => 'Analyze results',
        ],
    ];

    foreach ($expected as $locale => $texts) {
        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => $locale])
            ->get('/teacher/tools/assessment-results-analyzer');

        $response->assertSuccessful();
        $response->assertSee($texts['title']);
        $response->assertSee($texts['execute']);
    }
});

it('requires browser authentication', function () {
    $response = $this->get('/teacher/tools/assessment-results-analyzer');

    $response->assertRedirect('/login');
});
