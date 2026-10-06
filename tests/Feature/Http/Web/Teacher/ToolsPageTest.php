<?php

use App\Models\User;
use Database\Seeders\AssessmentPositioningBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('renders the teacher tools page', function () {
    $user = User::factory()->create();

    (new AssessmentPositioningBlueprintSeeder())->run();

    $response = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'en')
        ->get('/teacher/tools');

    $response->assertSuccessful();
    $response->assertSee('Teacher Tools');
    $response->assertSee('/api/teacher/blueprints/discover', false);
    $response->assertSee('/teacher/tools/assessment-positioning', false);
});

it('requires browser authentication', function () {
    $response = $this->get('/teacher/tools');

    $response->assertRedirect('/login');
});


it('changes the browser locale', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post('/locale', [
            'locale' => 'fr',
        ]);

    $response->assertSessionHas('locale', 'fr');
});
