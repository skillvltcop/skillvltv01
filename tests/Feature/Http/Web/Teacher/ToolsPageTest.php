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
        ->withSession(['locale' => 'en'])
        ->get('/teacher/tools');

    $response->assertSuccessful();
    $response->assertSee('Teacher Tools');
    $response->assertSee('/api/teacher/blueprints/discover', false);
});

it('guards discovery payload shape and skips empty tool entries', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get('/teacher/tools');

    $response->assertSuccessful();
    $response->assertSee('response.json().catch(() => ({}))', false);
    $response->assertSee('Array.isArray(payload.data)', false);
    $response->assertSee("typeof blueprint.slug !== 'string'", false);
    $response->assertSee("blueprint.slug.trim() === ''", false);
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

it('preserves the current locale when an unsupported locale is submitted', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['locale' => 'fr'])
        ->from('/teacher/tools')
        ->post('/locale', [
            'locale' => 'de',
        ]);

    $response->assertRedirect('/teacher/tools');
    $response->assertSessionHas('locale', 'fr');
});
