<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('shows the password reset request page to guests', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('Forgot your password?')
        ->assertSee('/forgot-password');
});

it('sends reset notifications for registered email addresses', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'teacher@example.com',
    ]);

    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not disclose whether an email address is registered', function () {
    Notification::fake();

    $knownUser = User::factory()->create(['email' => 'known@example.com']);

    $known = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => 'known@example.com',
    ]);

    $unknown = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => 'unknown@example.com',
    ]);

    $known->assertRedirect('/forgot-password')->assertSessionHas('status');
    $unknown->assertRedirect('/forgot-password')->assertSessionHas('status');
    expect($known->getSession()->get('status'))->toBe($unknown->getSession()->get('status'));
    Notification::assertSentTo($knownUser, ResetPassword::class);
    expect(Notification::sent($knownUser, ResetPassword::class))->toHaveCount(1);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'old-password',
    ]);
    $token = Password::createToken($user);
    $apiToken = $user->createToken('before-password-reset')->plainTextToken;
    $originalRememberToken = $user->remember_token;

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHas('status');

    $user->refresh();
    expect(Hash::check('new-secure-password', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe($originalRememberToken);
    expect(Password::tokenExists($user, $token))->toBeFalse();
    $this->assertDatabaseCount('personal_access_tokens', 0);

    $this->withToken($apiToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('rejects an invalid password reset token', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.com',
        'password' => 'old-password',
    ]);

    $response = $this->from('/reset-password/invalid-token')->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertRedirect('/reset-password/invalid-token');
    $response->assertSessionHasErrors('email');
    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

it('requires matching passwords with at least eight characters', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->from('/reset-password/'.$token)
        ->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertRedirect('/reset-password/'.$token)
        ->assertSessionHasErrors(['password']);
});

it('rate limits password reset requests', function () {
    foreach (range(1, 3) as $attempt) {
        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertRedirect('/forgot-password');
    }

    $this->post('/forgot-password', [
        'email' => 'unknown@example.com',
    ])->assertTooManyRequests();
});
