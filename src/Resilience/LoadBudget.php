<?php

declare(strict_types=1);

namespace Sabri\CF02\Resilience;

use InvalidArgumentException;

final class LoadBudget
{
    public function __construct(
        private readonly int $maxRequestsPerMinute,
        private readonly int $maxP95Milliseconds,
        private readonly int $maxQueueDepth,
        private readonly float $maxErrorRate,
        private readonly int $maxWorkerLagSeconds
    ) {
        if ($maxRequestsPerMinute < 1 || $maxRequestsPerMinute > 1_000_000
            || $maxP95Milliseconds < 50 || $maxP95Milliseconds > 60_000
            || $maxQueueDepth < 1 || $maxQueueDepth > 10_000_000
            || $maxErrorRate < 0.0 || $maxErrorRate > 1.0
            || $maxWorkerLagSeconds < 1 || $maxWorkerLagSeconds > 86400) {
            throw new InvalidArgumentException('Invalid load budget.');
        }
    }

    public function evaluate(
        int $requestsPerMinute,
        int $p95Milliseconds,
        int $queueDepth,
        float $errorRate,
        int $workerLagSeconds
    ): array {
        $violations = [];
        if ($requestsPerMinute > $this->maxRequestsPerMinute) { $violations[] = 'request_rate'; }
        if ($p95Milliseconds > $this->maxP95Milliseconds) { $violations[] = 'p95_latency'; }
        if ($queueDepth > $this->maxQueueDepth) { $violations[] = 'queue_depth'; }
        if ($errorRate > $this->maxErrorRate) { $violations[] = 'error_rate'; }
        if ($workerLagSeconds > $this->maxWorkerLagSeconds) { $violations[] = 'worker_lag'; }
        return [
            'passed' => $violations === [],
            'violations' => $violations,
            'action' => $violations === [] ? 'continue_monitored_operation' : 'degrade_noncritical_work_and_escalate',
        ];
    }
}
