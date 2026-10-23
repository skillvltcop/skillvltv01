<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires authentication and super administrator role for the authorization dashboard', function () {
    $this->get('/admin/authorization')->assertRedirect('/login');

    $user = User::factory()->create()->refresh();

    $this->actingAs($user)
        ->get('/admin/authorization')
        ->assertForbidden();

    $superAdmin = User::factory()->create();
    $superAdmin->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

    $this->actingAs($superAdmin)
        ->get('/admin/authorization')
        ->assertSuccessful()
        ->assertSee('إدارة الصلاحيات')
        ->assertSee('سجل التدقيق');
});
