<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not grant named administrative permissions to a regular user', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe('user')
        ->and($user->hasPermissionTo('blueprint.system.read_private'))->toBeFalse()
        ->and($user->hasPermissionTo('blueprint.system.create'))->toBeFalse();
});

it('grants an administrator only explicitly assigned permissions', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.read_private'],
    ])->save();
    $user->refresh();

    expect($user->hasPermissionTo('blueprint.system.read_private'))->toBeTrue()
        ->and($user->hasPermissionTo('blueprint.system.create'))->toBeFalse();
});

it('allows a super administrator to pass named permission checks', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'role' => 'super_admin',
        'permissions' => [],
    ])->save();
    $user->refresh();

    expect($user->hasPermissionTo('blueprint.system.read_private'))->toBeTrue()
        ->and($user->hasPermissionTo('blueprint.system.create'))->toBeTrue();
});
