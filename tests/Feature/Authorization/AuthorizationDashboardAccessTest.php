<?php

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('restricts the authorization dashboard to super administrators', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['teacher_tools.manage_registry'],
    ])->save();

    $this->actingAs($user)
        ->get('/admin/authorization')
        ->assertForbidden();

    $this->actingAs($admin)
        ->get('/admin/authorization')
        ->assertForbidden();
});

it('renders the authorization dashboard for a super administrator', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->forceFill([
        'role' => 'super_admin',
        'permissions' => [],
    ])->save();

    $this->actingAs($superAdmin)
        ->get('/admin/authorization')
        ->assertOk()
        ->assertSee('إدارة الصلاحيات');
});
