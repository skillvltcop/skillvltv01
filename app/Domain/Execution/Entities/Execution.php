<?php

declare(strict_types=1);

namespace App\Domain\Execution\Entities;

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\ValueObjects\ExecutionId;

final class Execution
{
    private function __construct(
        private ExecutionId $id,
        private BlueprintId $blueprintId,
        private RevisionId $revisionId,
        private array $input,
        private array $context,
        private ExecutionStatus $status,
        private ?array $output,
        private ?string $error,
    ) {
    }

    public static function create(
        BlueprintId $blueprintId,
        RevisionId $revisionId,
        array $input,
        array $context,
    ): self {
        return new self(
            id: ExecutionId::generate(),
            blueprintId: $blueprintId,
            revisionId: $revisionId,
            input: self::copy($input),
            context: self::copy($context),
            status: ExecutionStatus::PENDING,
            output: null,
            error: null,
        );
    }

    public function id(): ExecutionId
    {
        return $this->id;
    }

    public function blueprintId(): BlueprintId
    {
        return $this->blueprintId;
    }

    public function revisionId(): RevisionId
    {
        return $this->revisionId;
    }

    public function input(): array
    {
        return self::copy($this->input);
    }

    public function context(): array
    {
        return self::copy($this->context);
    }

    public function status(): ExecutionStatus
    {
        return $this->status;
    }

    public function output(): ?array
    {
        return $this->output === null
            ? null
            : self::copy($this->output);
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function start(): void
    {
        if ($this->status !== ExecutionStatus::PENDING) {
            throw new \DomainException(
                'Only a pending execution can be started.'
            );
        }

        $this->status = ExecutionStatus::RUNNING;
    }

    public function complete(array $output): void
    {
        if ($this->status !== ExecutionStatus::RUNNING) {
            throw new \DomainException(
                'Only a running execution can be completed.'
            );
        }

        $this->output = self::copy($output);
        $this->error = null;
        $this->status = ExecutionStatus::COMPLETED;
    }

    public function fail(string $error): void
    {
        if ($this->status !== ExecutionStatus::RUNNING) {
            throw new \DomainException(
                'Only a running execution can fail.'
            );
        }

        $this->output = null;
        $this->error = $error;
        $this->status = ExecutionStatus::FAILED;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function copy(array $value): array
    {
        return unserialize(serialize($value));
    }

    public static function reconstitute(
        ExecutionId $id,
        BlueprintId $blueprintId,
        RevisionId $revisionId,
        array $input,
        array $context,
        ExecutionStatus $status,
        ?array $output,
        ?string $error,
    ): self {
        return new self(
            id: $id,
            blueprintId: $blueprintId,
            revisionId: $revisionId,
            input: self::copy($input),
            context: self::copy($context),
            status: $status,
            output: $output === null
                ? null
                : self::copy($output),
            error: $error,
        );
    }

}