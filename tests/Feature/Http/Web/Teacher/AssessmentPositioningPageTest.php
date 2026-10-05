<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('renders the assessment positioning teacher page', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->get('/teacher/tools/assessment-positioning');

    $response->assertSuccessful();
    $response->assertSee('Assessment Positioning');
    $response->assertSee('/api/teacher/blueprints/discover', false);
    $response->assertSee('/api/teacher/tools/assessment-positioning/execute', false);
    $response->assertSee('تحديد التموضع');
});

it('requires browser authentication', function () {
    $response = $this->get('/teacher/tools/assessment-positioning');

    $response->assertRedirect('/login');
});
