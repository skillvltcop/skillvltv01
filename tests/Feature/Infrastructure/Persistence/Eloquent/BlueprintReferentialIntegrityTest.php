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
});
