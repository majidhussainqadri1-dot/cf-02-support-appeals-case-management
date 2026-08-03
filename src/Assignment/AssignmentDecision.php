<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class AssignmentDecision
{
    /** @param list<string> $reasons */
    private function __construct(
        private readonly SupportCaseId $caseId,
        private readonly ?string $agentReference,
        private readonly string $queueKey,
        private readonly int $score,
        private readonly int $eligibleCandidates,
        private readonly array $reasons,
        private readonly DateTimeImmutable $decidedAt
    ) {
        if ($score < 0 || $eligibleCandidates < 0) {
            throw new InvalidArgumentException('Assignment score and candidate count must be non-negative.');
        }
    }

    /** @param list<string> $reasons */
    public static function assigned(
        SupportCaseId $caseId,
        string $agentReference,
        string $queueKey,
        int $score,
        int $eligibleCandidates,
        array $reasons,
        DateTimeImmutable $decidedAt
    ): self {
        if (trim($agentReference) === '') {
            throw new InvalidArgumentException('Assigned agent reference is required.');
        }
        return new self($caseId, $agentReference, $queueKey, $score, $eligibleCandidates, $reasons, $decidedAt);
    }

    /** @param list<string> $reasons */
    public static function unassigned(
        SupportCaseId $caseId,
        string $queueKey,
        array $reasons,
        DateTimeImmutable $decidedAt
    ): self {
        return new self($caseId, null, $queueKey, 0, 0, $reasons, $decidedAt);
    }

    public function isAssigned(): bool { return $this->agentReference !== null; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function agentReference(): ?string { return $this->agentReference; }
    public function queueKey(): string { return $this->queueKey; }
    public function score(): int { return $this->score; }
    public function eligibleCandidates(): int { return $this->eligibleCandidates; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    public function decidedAt(): DateTimeImmutable { return $this->decidedAt; }
}
