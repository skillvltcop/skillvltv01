<?php

use App\Models\Blueprint;
use App\Models\BlueprintRevision;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createIntegrityBlueprint(string $name): Blueprint
{
    return Blueprint::query()->create([
        'id' => (string) Str::ulid(),
        'canonical_name' => $name,
        'namespace' => 'skillvlt.test.integrity',
        'owner_type' => 'system',
        'owner_id' => 'skillvlt',
        'lifecycle_status' => 'draft',
    ]);
}

function createIntegrityRevision(Blueprint $blueprint, string $number, ?string $parentId = null): BlueprintRevision
{
    return BlueprintRevision::query()->create([
        'id' => (string) Str::ulid(),
        'blueprint_id' => (string) $blueprint->id,
        'revision_number' => $number,
        'parent_revision_id' => $parentId,
        'behavior_digest' => 'sha256:' . str_repeat('a', 64),
        'contracts' => [],
        'logic' => [],
        'outputs' => [],
        'policies' => [],
        'frozen' => false,
    ]);
}

it('prevents a blueprint from referencing another blueprint current revision', function () {
    $first = createIntegrityBlueprint('integrity-first-current');
    $second = createIntegrityBlueprint('integrity-second-current');
    $foreignRevision = createIntegrityRevision($second, '1.0.0');

    expect(fn () => $first->update([
        'current_revision_id' => (string) $foreignRevision->id,
    ]))->toThrow(QueryException::class);
});

it('prevents a revision from using a parent belonging to another blueprint', function () {
    $first = createIntegrityBlueprint('integrity-first-parent');
    $second = createIntegrityBlueprint('integrity-second-parent');
    $foreignParent = createIntegrityRevision($second, '1.0.0');

    expect(fn () => createIntegrityRevision(
        $first,
        '1.0.0',
        (string) $foreignParent->id,
    ))->toThrow(QueryException::class);
});

it('prevents an execution from referencing a revision belonging to another blueprint', function () {
    $first = createIntegrityBlueprint('integrity-first-execution');
    $second = createIntegrityBlueprint('integrity-second-execution');
    $foreignRevision = createIntegrityRevision($second, '1.0.0');

    expect(fn () => DB::table('executions')->insert([
        'id' => (string) Str::ulid(),
        'blueprint_id' => (string) $first->id,
        'revision_id' => (string) $foreignRevision->id,
        'owner_id' => 'integrity-owner',
        'input' => json_encode([]),
        'context' => json_encode([]),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces revision number uniqueness within a blueprint at the database level', function () {
    $blueprint = createIntegrityBlueprint('integrity-duplicate-number');

    createIntegrityRevision($blueprint, '1.0.0');

    expect(fn () => createIntegrityRevision(
        $blueprint,
        '1.0.0',
    ))->toThrow(QueryException::class);
});

it('prevents deleting a blueprint that has executions', function () {
    $blueprint = createIntegrityBlueprint('integrity-blueprint-with-execution');
    $revision = createIntegrityRevision($blueprint, '1.0.0');
    $blueprint->update(['current_revision_id' => (string) $revision->id]);

    DB::table('executions')->insert([
        'id' => (string) Str::ulid(),
        'blueprint_id' => (string) $blueprint->id,
        'revision_id' => (string) $revision->id,
        'owner_id' => null,
        'input' => json_encode([]),
        'context' => json_encode([]),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $blueprint->delete())
        ->toThrow(QueryException::class);

    expect(Blueprint::query()->whereKey((string) $blueprint->id)->exists())
        ->toBeTrue();

    expect($blueprint->fresh()->current_revision_id)
        ->toBe((string) $revision->id);
});

it('cascades blueprint deletion to revisions and metadata when no executions exist', function () {
    $blueprint = createIntegrityBlueprint('integrity-blueprint-cascade-delete');
    $revision = createIntegrityRevision($blueprint, '1.0.0');

    DB::table('blueprint_metadata')->insert([
        'blueprint_id' => (string) $blueprint->id,
        'taxonomy' => json_encode([]),
        'documentation' => json_encode([]),
        'discovery' => null,
        'lifecycle_metadata' => json_encode([]),
        'payload' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $blueprint->delete();

    expect(Blueprint::query()->whereKey((string) $blueprint->id)->exists())
        ->toBeFalse();

    expect(BlueprintRevision::query()->whereKey((string) $revision->id)->exists())
        ->toBeFalse();

    expect(DB::table('blueprint_metadata')
        ->where('blueprint_id', (string) $blueprint->id)
        ->exists())->toBeFalse();
});

it('cascades blueprint deletion when its current revision is set and no executions exist', function () {
    $blueprint = createIntegrityBlueprint('integrity-blueprint-current-cascade-delete');
    $revision = createIntegrityRevision($blueprint, '1.0.0');

    $blueprint->update([
        'current_revision_id' => (string) $revision->id,
    ]);

    $blueprint->delete();

    expect(Blueprint::query()->whereKey((string) $blueprint->id)->exists())
        ->toBeFalse();

    expect(BlueprintRevision::query()->whereKey((string) $revision->id)->exists())
        ->toBeFalse();
});
