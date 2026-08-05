<?php

declare(strict_types=1);

namespace Sabri\CF02\Retention;

use InvalidArgumentException;

final class RetentionPolicy
{
    public function __construct(
        private readonly string $category,
        private readonly int $openCaseDays,
        private readonly int $closedCaseDays,
        private readonly int $attachmentDays,
        private readonly int $auditDays,
        private readonly bool $preserveDecisionEvidence
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $category) !== 1) {
            throw new InvalidArgumentException('Invalid retention category.');
        }
        foreach ([$openCaseDays, $closedCaseDays, $attachmentDays, $auditDays] as $days) {
            if ($days < 1 || $days > 3650) {
                throw new InvalidArgumentException('Retention period must be between one day and ten years.');
            }
        }
        if ($attachmentDays > $closedCaseDays) {
            throw new InvalidArgumentException('Attachment retention cannot exceed closed-case retention.');
        }
        if ($auditDays < $closedCaseDays) {
            throw new InvalidArgumentException('Audit retention cannot be shorter than closed-case retention.');
        }
    }

    public function category(): string { return $this->category; }
    public function openCaseDays(): int { return $this->openCaseDays; }
    public function closedCaseDays(): int { return $this->closedCaseDays; }
    public function attachmentDays(): int { return $this->attachmentDays; }
    public function auditDays(): int { return $this->auditDays; }
    public function preserveDecisionEvidence(): bool { return $this->preserveDecisionEvidence; }
}
