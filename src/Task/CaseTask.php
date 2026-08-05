<?php

declare(strict_types=1);

namespace Sabri\CF02\Task;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseTask
{
    private int $version = 1;
    private string $state = 'open';
    private ?string $outcomeReference = null;
    private ?DateTimeImmutable $completedAt = null;

    public function __construct(
        private readonly string $taskId,
        private readonly SupportCaseId $caseId,
        private readonly string $taskType,
        private readonly ?string $assigneeReference,
        private readonly ?string $dependencyReference,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $dueAt
    ) {
        if (preg_match('/^CF02-TASK-[A-F0-9]{20}$/', $taskId) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $taskType) !== 1) {
            throw new InvalidArgumentException('Invalid case-task identity.');
        }
        if ($assigneeReference === null && $dependencyReference === null) {
            throw new InvalidArgumentException('Task requires an assignee or dependency owner.');
        }
        if ($dueAt !== null && $dueAt <= $createdAt) {
            throw new InvalidArgumentException('Task due time must follow creation.');
        }
    }

    public static function create(
        SupportCaseId $caseId,
        string $taskType,
        ?string $assigneeReference,
        ?string $dependencyReference,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $dueAt
    ): self {
        return new self('CF02-TASK-' . strtoupper(bin2hex(random_bytes(10))), $caseId, $taskType, $assigneeReference, $dependencyReference, $createdAt, $dueAt);
    }

    public function block(string $dependencyReference, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if ($this->state !== 'open' || trim($dependencyReference) === '') {
            throw new DomainException('Only an open task may become blocked by a declared dependency.');
        }
        $this->state = 'blocked';
        ++$this->version;
    }

    public function resume(int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if ($this->state !== 'blocked') {
            throw new DomainException('Only a blocked task may resume.');
        }
        $this->state = 'open';
        ++$this->version;
    }

    public function complete(string $outcomeReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if ($this->state !== 'open' || trim($outcomeReference) === '' || $at < $this->createdAt) {
            throw new DomainException('Open task, outcome reference and valid chronology are required.');
        }
        $this->state = 'completed';
        $this->outcomeReference = trim($outcomeReference);
        $this->completedAt = $at;
        ++$this->version;
    }

    public function cancel(string $reason, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (!in_array($this->state, ['open', 'blocked'], true) || trim($reason) === '') {
            throw new DomainException('Open or blocked task and cancellation reason are required.');
        }
        $this->state = 'cancelled';
        $this->outcomeReference = 'cancelled:' . hash('sha256', trim($reason));
        ++$this->version;
    }

    public function isOverdue(DateTimeImmutable $at): bool
    {
        return $this->dueAt !== null && !in_array($this->state, ['completed', 'cancelled'], true) && $at > $this->dueAt;
    }

    public function taskId(): string { return $this->taskId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function state(): string { return $this->state; }
    public function version(): int { return $this->version; }
    public function outcomeReference(): ?string { return $this->outcomeReference; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale task version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }
}
