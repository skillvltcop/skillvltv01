<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Domain\Blueprint\Enums\LifecycleStatus;

final class Blueprint
{
    /**
     * @var array<string, mixed>
     */
    private array $ownership;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @var array<string, BlueprintRevision>
     */
    private array $revisions = [];

    private LifecycleStatus $lifecycleStatus;

    private ?RevisionId $currentRevisionId = null;

    private function __construct(
        private readonly BlueprintId $id,
        private readonly CanonicalName $canonicalName,
        private readonly BlueprintNamespace $namespace,
        array $ownership,
        array $metadata,
    ) {
        $this->ownership = self::copy($ownership);
        $this->metadata = self::copy($metadata);
        $this->lifecycleStatus = LifecycleStatus::DRAFT;
    }

    /**
     * Create a new Blueprint.
     *
     * @param array<string, mixed> $ownership
     * @param array<string, mixed> $metadata
     */
    public static function create(
        CanonicalName $canonicalName,
        BlueprintNamespace $namespace,
        array $ownership,
        array $metadata = [],
    ): self {
        return new self(
            id: BlueprintId::generate(),
            canonicalName: $canonicalName,
            namespace: $namespace,
            ownership: $ownership,
            metadata: $metadata,
        );
    }

    public function id(): BlueprintId
    {
        return $this->id;
    }

    public function canonicalName(): CanonicalName
    {
        return $this->canonicalName;
    }

    public function namespace(): BlueprintNamespace
    {
        return $this->namespace;
    }

    /**
     * @return array<string, mixed>
     */
    public function ownership(): array
    {
        return self::copy($this->ownership);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return self::copy($this->metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateMetadata(array $metadata): void
    {
        $this->metadata = self::copy($metadata);
    }

    public function addRevision(
        RevisionNumber $number,
        BehaviorDigest $behaviorDigest,
        array $contracts,
        array $logic,
        array $outputs,
        array $policies,
    ): BlueprintRevision {
        $parentRevisionId = $this->latestRevisionId();

        $revision = BlueprintRevision::create(
            number: $number,
            parentRevisionId: $parentRevisionId,
            behaviorDigest: $behaviorDigest,
            contracts: $contracts,
            logic: $logic,
            outputs: $outputs,
            policies: $policies,
        );

        $this->revisions[(string) $revision->id()] = $revision;

        return $revision;
    }

    /**
     * @return array<string, BlueprintRevision>
     */
    public function revisions(): array
    {
        return $this->revisions;
    }

    public function revision(RevisionId $id): ?BlueprintRevision
    {
        return $this->revisions[(string) $id] ?? null;
    }

    public function latestRevision(): ?BlueprintRevision
    {
        if ($this->revisions === []) {
            return null;
        }

        $revisions = array_values($this->revisions);

        return $revisions[array_key_last($revisions)];
    }

    private function latestRevisionId(): ?RevisionId
    {
        return $this->latestRevision()?->id();
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function copy(array $value): array
    {
        return unserialize(serialize($value));
    }

    public function promoteRevision(RevisionId $revisionId): void
    {
        $revision = $this->revision($revisionId);

        if ($revision === null) {
            throw new \DomainException(
                'Blueprint revision not found.'
            );
        }

        if (! $revision->isFrozen()) {
            throw new \DomainException(
                'A Revision must be frozen before it can become current.'
            );
        }

        $this->currentRevisionId = $revision->id();
    }

    public function lifecycleStatus(): LifecycleStatus
    {
        return $this->lifecycleStatus;
    }

    public function activate(): void
    {
        $currentRevision = $this->currentRevision();

        if ($currentRevision === null) {
            throw new \DomainException(
                'A Blueprint cannot become active without a Revision.'
            );
        }

        if (! $currentRevision->isFrozen()) {
            throw new \DomainException(
                'A Blueprint cannot become active with an unfrozen current Revision.'
            );
        }

        $this->transitionTo(LifecycleStatus::ACTIVE);
    }

    public function deprecate(): void
    {
        $this->transitionTo(LifecycleStatus::DEPRECATED);
    }

    public function sunset(): void
    {
        $this->transitionTo(LifecycleStatus::SUNSET);
    }

    private function transitionTo(LifecycleStatus $target): void
    {
        $allowed = match ($this->lifecycleStatus) {
            LifecycleStatus::DRAFT => [
                LifecycleStatus::ACTIVE,
            ],

            LifecycleStatus::ACTIVE => [
                LifecycleStatus::DEPRECATED,
                LifecycleStatus::SUNSET,
            ],

            LifecycleStatus::DEPRECATED => [
                LifecycleStatus::SUNSET,
            ],

            LifecycleStatus::SUNSET => [],
        };

        if (! in_array($target, $allowed, true)) {
            throw new \DomainException(
                sprintf(
                    'Invalid Blueprint lifecycle transition: %s → %s.',
                    $this->lifecycleStatus->value,
                    $target->value,
                )
            );
        }

        $this->lifecycleStatus = $target;
    }

    public function currentRevisionId(): ?RevisionId
    {
        return $this->currentRevisionId;
    }

    public function currentRevision(): ?BlueprintRevision
    {
        if ($this->currentRevisionId === null) {
            return null;
        }

        return $this->revision($this->currentRevisionId);
    }

    /**
     * Reconstitute an existing Blueprint from persisted state.
     *
     * This method must never generate a new identity.
     *
     * @param array<string, mixed> $ownership
     * @param array<string, mixed> $metadata
     */
    public static function reconstitute(
        BlueprintId $id,
        CanonicalName $canonicalName,
        BlueprintNamespace $namespace,
        array $ownership,
        array $metadata,
        LifecycleStatus $lifecycleStatus,
        ?RevisionId $currentRevisionId,
        array $revisions = [],
    ): self {
        $blueprint = new self(
            id: $id,
            canonicalName: $canonicalName,
            namespace: $namespace,
            ownership: $ownership,
            metadata: $metadata,
        );

        $blueprint->lifecycleStatus = $lifecycleStatus;
        $blueprint->currentRevisionId = $currentRevisionId;
        $blueprint->revisions = $revisions;

        return $blueprint;
    }
}