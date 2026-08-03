<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class AssignmentRequest
{
    /** @param list<string> $requiredSkills */
    public function __construct(
        private readonly SupportCaseId $caseId,
        private readonly string $queueKey,
        private readonly array $requiredSkills,
        private readonly string $preferredLanguage,
        private readonly string $priority,
        private readonly bool $specialistRequired,
        private readonly bool $sensitive
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1) {
            throw new InvalidArgumentException('Invalid assignment queue.');
        }
        if ($requiredSkills === []) {
            throw new InvalidArgumentException('Assignment requires at least one skill.');
        }
        foreach ($requiredSkills as $skill) {
            if (!is_string($skill) || preg_match('/^[a-z][a-z0-9_]*$/', $skill) !== 1) {
                throw new InvalidArgumentException('Invalid assignment skill.');
            }
        }
        if (count($requiredSkills) !== count(array_unique($requiredSkills))) {
            throw new InvalidArgumentException('Duplicate assignment skills are prohibited.');
        }
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $preferredLanguage) !== 1) {
            throw new InvalidArgumentException('Invalid preferred language.');
        }
        if (!in_array($priority, ['P1', 'P2', 'P3', 'P4'], true)) {
            throw new InvalidArgumentException('Invalid assignment priority.');
        }
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function queueKey(): string { return $this->queueKey; }
    /** @return list<string> */ public function requiredSkills(): array { return $this->requiredSkills; }
    public function preferredLanguage(): string { return $this->preferredLanguage; }
    public function priority(): string { return $this->priority; }
    public function specialistRequired(): bool { return $this->specialistRequired; }
    public function sensitive(): bool { return $this->sensitive; }
}
