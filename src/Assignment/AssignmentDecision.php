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
        private readonly bool $restrictedAccessApproved,
        private readonly array $reasons,
        private readonly DateTimeImmutable $decidedAt,
        private readonly DateTimeImmutable $validUntil
    ) {
        if ($score < 0 || $eligibleCandidates < 0) {
            throw new InvalidArgumentException('Assignment score and candidate count must be non-negative.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1 || $reasons === []) {
            throw new InvalidArgumentException('Assignment queue and reasons are required.');
        }
        foreach ($reasons as $reason) {
            if (!is_string($reason) || trim($reason) === '') {
                throw new InvalidArgumentException('Invalid assignment reason.');
            }
        }
        if ($validUntil < $decidedAt) {
            throw new InvalidArgumentException('Assignment decision validity cannot end before decision time.');
        }
        if ($agentReference === null && $restrictedAccessApproved) {
            throw new InvalidArgumentException('Unassigned decision cannot approve restricted access.');
        }
    }

    /** @param list<string> $reasons */
    public static function assigned(
        SupportCaseId $caseId,
        string $agentReference,
        string $queueKey,
        int $score,
        int $eligibleCandidates,
        bool $restrictedAccessApproved,
        array $reasons,
        DateTimeImmutable $decidedAt,
        DateTimeImmutable $validUntil
    ): self {
        if (trim($agentReference) === '' || $eligibleCandidates < 1 || $validUntil <= $decidedAt) {
            throw new InvalidArgumentException('Assigned agent, positive candidates and future validity are required.');
        }
        return new self($caseId, $agentReference, $queueKey, $score, $eligibleCandidates, $restrictedAccessApproved, $reasons, $decidedAt, $validUntil);
    }

    /** @param list<string> $reasons */
    public static function unassigned(
        SupportCaseId $caseId,
        string $queueKey,
        array $reasons,
        DateTimeImmutable $decidedAt
    ): self {
        return new self($caseId, null, $queueKey, 0, 0, false, $reasons, $decidedAt, $decidedAt);
    }

    public function isAssigned(): bool { return $this->agentReference !== null; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function agentReference(): ?string { return $this->agentReference; }
    public function queueKey(): string { return $this->queueKey; }
    public function score(): int { return $this->score; }
    public function eligibleCandidates(): int { return $this->eligibleCandidates; }
    public function restrictedAccessApproved(): bool { return $this->restrictedAccessApproved; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    public function decidedAt(): DateTimeImmutable { return $this->decidedAt; }
    public function validUntil(): DateTimeImmutable { return $this->validUntil; }
    public function isValidAt(DateTimeImmutable $at): bool { return $at >= $this->decidedAt && $at <= $this->validUntil; }
}
