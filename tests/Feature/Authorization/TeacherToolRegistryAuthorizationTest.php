<?php

use App\Authorization\TeacherToolRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows a specifically authorized administrator to disable a teacher tool with an audit reason', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['teacher_tools.manage_registry'],
    ])->save();

    expect(app(TeacherToolRegistry::class)->contains('assessment-positioning'))->toBeTrue();

    $this->actingAs($admin)
        ->putJson('/api/admin/teacher-tools/assessment-positioning', [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-positioning',
            'enabled' => false,
            'reason' => 'Temporarily disable pending review',
        ])
        ->assertSuccessful()
        ->assertJsonPath('enabled', false);

    expect(app(TeacherToolRegistry::class)->contains('assessment-positioning'))->toBeFalse()
        ->and(DB::table('authorization_audit_events')
            ->where('action', 'change_teacher_tool_registry')
            ->where('resource_id', 'assessment-positioning')
            ->where('reason', 'Temporarily disable pending review')
            ->exists())->toBeTrue();
});

it('forbids administrators without the registry permission from changing teacher tools', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ])->save();

    $this->actingAs($admin)
        ->putJson('/api/admin/teacher-tools/assessment-positioning', [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-positioning',
            'enabled' => false,
            'reason' => 'Attempt unauthorized registry change',
        ])
        ->assertForbidden();

    expect(app(TeacherToolRegistry::class)->contains('assessment-positioning'))->toBeTrue();
});
