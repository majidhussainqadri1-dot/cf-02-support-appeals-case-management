<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

final class TriageDecision
{
    /** @param list<string> $reasons */
    public function __construct(
        private readonly string $queueKey,
        private readonly string $priority,
        private readonly string $severity,
        private readonly bool $specialistRequired,
        private readonly bool $humanReviewRequired,
        private readonly array $reasons
    ) {
    }

    public function queueKey(): string { return $this->queueKey; }
    public function priority(): string { return $this->priority; }
    public function severity(): string { return $this->severity; }
    public function specialistRequired(): bool { return $this->specialistRequired; }
    public function humanReviewRequired(): bool { return $this->humanReviewRequired; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
}
