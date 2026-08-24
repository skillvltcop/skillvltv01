<?php

declare(strict_types=1);

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;

final class InMemoryBlueprintRepository implements BlueprintRepository
{
    /**
     * @var array<string, Blueprint>
     */
    private array $items = [];

    public function find(BlueprintId $id): ?Blueprint
    {
        return $this->items[(string) $id] ?? null;
    }

    public function findOwnedBy(
        string $ownerType,
        string $ownerId,
    ): array {
        return array_values(
            array_filter(
                $this->items,
                static function (Blueprint $blueprint) use (
                    $ownerType,
                    $ownerId,
                ): bool {
                    $ownership = $blueprint->ownership();

                    return $ownership['type'] === $ownerType
                        && (string) $ownership['id'] === $ownerId;
                },
            ),
        );
    }

    public function save(Blueprint $blueprint): void
    {
        $this->items[(string) $blueprint->id()] = $blueprint;
    }
    
    public function discover(): array
    {
        return array_values(
            array_filter(
                $this->items,
                static function (Blueprint $blueprint): bool {
                    $ownership = $blueprint->ownership();

                    return $ownership['type'] === 'system'
                        && (string) $ownership['id'] === 'skillvlt';
                },
            ),
        );
    }
}

it('defines the blueprint repository contract', function () {
    $repository = new InMemoryBlueprintRepository();

    expect($repository)
        ->toBeInstanceOf(BlueprintRepository::class);
});

it('can save and retrieve a blueprint through the repository contract', function () {
    $repository = new InMemoryBlueprintRepository();

    $blueprint = Blueprint::create(
        canonicalName: new \App\Domain\Blueprint\ValueObjects\CanonicalName(
            'assessment-rubric-core'
        ),
        namespace: new \App\Domain\Blueprint\ValueObjects\BlueprintNamespace(
            'skillvlt.edu.assessment'
        ),
        ownership: [
            'owner_id' => 'skillvlt',
            'owner_type' => 'platform',
        ],
    );

    $repository->save($blueprint);

    $restored = $repository->find($blueprint->id());

    expect($restored)
        ->toBe($blueprint);
});

it('returns null when a blueprint does not exist', function () {
    $repository = new InMemoryBlueprintRepository();

    $id = BlueprintId::generate();

    expect($repository->find($id))
        ->toBeNull();
});