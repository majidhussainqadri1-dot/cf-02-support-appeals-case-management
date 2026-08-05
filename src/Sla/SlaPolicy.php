<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

use InvalidArgumentException;

final class SlaPolicy
{
    /** @param list<SlaPauseReason> $pauseReasons */
    public function __construct(
        private readonly string $policyId,
        private readonly string $version,
        private readonly string $queueKey,
        private readonly string $priority,
        private readonly string $calendarReference,
        private readonly int $firstResponseMinutes,
        private readonly int $updateMinutes,
        private readonly int $resolutionMinutes,
        private readonly int $warningThresholdPercent,
        private readonly array $pauseReasons
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $policyId) !== 1 || preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new InvalidArgumentException('Invalid SLA policy identity or version.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1 || !in_array($priority, ['P1', 'P2', 'P3', 'P4'], true)) {
            throw new InvalidArgumentException('Invalid SLA queue or priority.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $calendarReference) !== 1) {
            throw new InvalidArgumentException('Invalid SLA calendar reference.');
        }
        if ($firstResponseMinutes < 1 || $updateMinutes < $firstResponseMinutes || $resolutionMinutes < $updateMinutes) {
            throw new InvalidArgumentException('SLA target ordering is invalid.');
        }
        if ($warningThresholdPercent < 1 || $warningThresholdPercent > 99) {
            throw new InvalidArgumentException('SLA warning threshold must be 1 through 99.');
        }
        if ($pauseReasons === []) {
            throw new InvalidArgumentException('SLA policy requires explicit pause reasons.');
        }
        foreach ($pauseReasons as $reason) {
            if (!$reason instanceof SlaPauseReason) {
                throw new InvalidArgumentException('Invalid SLA pause reason.');
            }
        }
        if (count($pauseReasons) !== count(array_unique(array_map(static fn (SlaPauseReason $reason): string => $reason->value, $pauseReasons)))) {
            throw new InvalidArgumentException('Duplicate SLA pause reasons are prohibited.');
        }
    }

    public function policyId(): string { return $this->policyId; }
    public function version(): string { return $this->version; }
    public function queueKey(): string { return $this->queueKey; }
    public function priority(): string { return $this->priority; }
    public function calendarReference(): string { return $this->calendarReference; }
    public function firstResponseMinutes(): int { return $this->firstResponseMinutes; }
    public function updateMinutes(): int { return $this->updateMinutes; }
    public function resolutionMinutes(): int { return $this->resolutionMinutes; }
    public function warningThresholdPercent(): int { return $this->warningThresholdPercent; }
    /** @return list<SlaPauseReason> */ public function pauseReasons(): array { return $this->pauseReasons; }

    public function allowsPause(SlaPauseReason $reason): bool
    {
        return in_array($reason, $this->pauseReasons, true);
    }
}
