<?php

declare(strict_types=1);

namespace Sabri\CF02\Escalation;

use InvalidArgumentException;

final class BreachPrediction
{
    /** @param list<string> $reasons */
    public function __construct(
        private readonly string $risk,
        private readonly int $remainingWorkingMinutes,
        private readonly int $estimatedDemandMinutes,
        private readonly array $reasons
    ) {
        if (!in_array($risk, ['normal', 'watch', 'high', 'breach'], true)) {
            throw new InvalidArgumentException('Invalid breach-prediction risk.');
        }
        if ($remainingWorkingMinutes < 0 || $estimatedDemandMinutes < 0) {
            throw new InvalidArgumentException('Breach-prediction minutes must be non-negative.');
        }
    }

    public function risk(): string { return $this->risk; }
    public function remainingWorkingMinutes(): int { return $this->remainingWorkingMinutes; }
    public function estimatedDemandMinutes(): int { return $this->estimatedDemandMinutes; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
}
