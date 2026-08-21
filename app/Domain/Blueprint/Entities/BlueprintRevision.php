<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

final class BlueprintRevision
{
    private bool $frozen = false;

    private function __construct(
        private readonly RevisionId $id,
        private readonly RevisionNumber $number,
        private readonly ?RevisionId $parentRevisionId,
        private readonly BehaviorDigest $behaviorDigest,
        private readonly array $contracts,
        private readonly array $logic,
        private readonly array $outputs,
        private readonly array $policies,
    ) {
    }

    public static function create(
        RevisionNumber $number,
        ?RevisionId $parentRevisionId,
        BehaviorDigest $behaviorDigest,
        array $contracts,
        array $logic,
        array $outputs,
        array $policies,
    ): self {
        return new self(
            RevisionId::generate(),
            $number,
            $parentRevisionId,
            $behaviorDigest,
            self::copy($contracts),
            self::copy($logic),
            self::copy($outputs),
            self::copy($policies),
        );
    }

    public function freeze(): void
    {
        if ($this->frozen) {
            throw new \DomainException(
                'Revision is already frozen.'
            );
        }

        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function id(): RevisionId
    {
        return $this->id;
    }

    public function number(): RevisionNumber
    {
        return $this->number;
    }

    public function parentRevisionId(): ?RevisionId
    {
        return $this->parentRevisionId;
    }

    public function behaviorDigest(): BehaviorDigest
    {
        return $this->behaviorDigest;
    }

    public function contracts(): array
    {
        return self::copy($this->contracts);
    }

    public function logic(): array
    {
        return self::copy($this->logic);
    }

    public function outputs(): array
    {
        return self::copy($this->outputs);
    }

    public function policies(): array
    {
        return self::copy($this->policies);
    }

    private static function copy(array $value): array
    {
        return unserialize(serialize($value));
    }

    /**
     * Reconstitute an existing Revision from persisted state.
     *
     * This method must never generate a new identity.
     *
     * @param array<string, mixed> $contracts
     * @param array<string, mixed> $logic
     * @param array<string, mixed> $outputs
     * @param array<string, mixed> $policies
     */
    public static function reconstitute(
        RevisionId $id,
        RevisionNumber $number,
        ?RevisionId $parentRevisionId,
        BehaviorDigest $behaviorDigest,
        array $contracts,
        array $logic,
        array $outputs,
        array $policies,
        bool $frozen,
    ): self {
        $revision = new self(
            id: $id,
            number: $number,
            parentRevisionId: $parentRevisionId,
            behaviorDigest: $behaviorDigest,
            contracts: $contracts,
            logic: $logic,
            outputs: $outputs,
            policies: $policies,
        );

        $revision->frozen = $frozen;

        return $revision;
    }
}