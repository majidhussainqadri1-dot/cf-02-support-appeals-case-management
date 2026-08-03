<?php

declare(strict_types=1);

namespace Sabri\CF02\Queue;

use DateTimeImmutable;
use InvalidArgumentException;

final class QueueHealthSnapshot
{
    public function __construct(
        private readonly string $queueKey,
        private readonly DateTimeImmutable $capturedAt,
        private readonly int $openCases,
        private readonly int $unassignedCases,
        private readonly int $p1UnassignedCases,
        private readonly int $atRiskCases,
        private readonly int $breachedCases,
        private readonly int $oldestOpenWorkingMinutes,
        private readonly int $availableAgents,
        private readonly int $totalCapacity,
        private readonly int $assignedLoad
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1) {
            throw new InvalidArgumentException('Invalid queue-health key.');
        }
        foreach ([$openCases, $unassignedCases, $p1UnassignedCases, $atRiskCases, $breachedCases, $oldestOpenWorkingMinutes, $availableAgents, $totalCapacity, $assignedLoad] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Queue-health metrics must be non-negative.');
            }
        }
        if ($unassignedCases > $openCases || $p1UnassignedCases > $unassignedCases || $atRiskCases > $openCases || $breachedCases > $openCases) {
            throw new InvalidArgumentException('Queue-health subset counts are inconsistent.');
        }
        if ($assignedLoad > $totalCapacity || ($totalCapacity === 0 && $assignedLoad !== 0)) {
            throw new InvalidArgumentException('Queue capacity metrics are inconsistent.');
        }
    }

    public function queueKey(): string { return $this->queueKey; }
    public function capturedAt(): DateTimeImmutable { return $this->capturedAt; }
    public function openCases(): int { return $this->openCases; }
    public function unassignedCases(): int { return $this->unassignedCases; }
    public function p1UnassignedCases(): int { return $this->p1UnassignedCases; }
    public function atRiskCases(): int { return $this->atRiskCases; }
    public function breachedCases(): int { return $this->breachedCases; }
    public function oldestOpenWorkingMinutes(): int { return $this->oldestOpenWorkingMinutes; }
    public function availableAgents(): int { return $this->availableAgents; }
    public function totalCapacity(): int { return $this->totalCapacity; }
    public function assignedLoad(): int { return $this->assignedLoad; }
    public function utilization(): float { return $this->totalCapacity === 0 ? 0.0 : $this->assignedLoad / $this->totalCapacity; }
}
