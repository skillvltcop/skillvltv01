<?php

use App\Authorization\TeacherToolRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows a specifically authorized administrator to disable and re-enable a teacher tool with audit reasons', function () {
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

    expect(app(TeacherToolRegistry::class)->contains('assessment-positioning'))->toBeFalse();

    $this->actingAs($admin)
        ->getJson('/api/admin/teacher-tools')
        ->assertSuccessful()
        ->assertJsonFragment([
            'slug' => 'assessment-positioning',
            'enabled' => false,
        ]);

    $this->actingAs($admin)
        ->putJson('/api/admin/teacher-tools/assessment-positioning', [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-positioning',
            'enabled' => true,
            'reason' => 'Re-enable after review is complete',
        ])
        ->assertSuccessful()
        ->assertJsonPath('enabled', true);

    expect(app(TeacherToolRegistry::class)->contains('assessment-positioning'))->toBeTrue()
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

it('forbids administrators without the registry permission from listing teacher tools', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['blueprint.system.create'],
    ])->save();

    $this->actingAs($admin)
        ->getJson('/api/admin/teacher-tools')
        ->assertForbidden();
});

it('removes a disabled teacher tool from discovery and blocks execution', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'role' => 'admin',
        'permissions' => ['teacher_tools.manage_registry'],
    ])->save();

    (new \Database\Seeders\AssessmentPositioningBlueprintSeeder())->run();

    $this->actingAs($admin)
        ->putJson('/api/admin/teacher-tools/assessment-positioning', [
            'namespace' => 'skillvlt.edu.assessment',
            'view' => 'teacher.tools.assessment-positioning',
            'enabled' => false,
            'reason' => 'Disable tool during a controlled review',
        ])
        ->assertSuccessful()
        ->assertJsonPath('enabled', false);

    $teacher = User::factory()->create();

    $discovery = $this->actingAs($teacher)
        ->getJson('/api/teacher/blueprints/discover')
        ->assertSuccessful();

    expect(collect($discovery->json('data'))->pluck('slug')->all())
        ->not->toContain('assessment-positioning');

    $this->actingAs($teacher)
        ->postJson('/api/teacher/tools/assessment-positioning/execute', [
            'input' => ['score' => 59, 'max_score' => 100],
            'context' => [],
        ])
        ->assertNotFound();
});
