<?php

declare(strict_types=1);

namespace Sabri\CF02\Escalation;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Sla\SlaClock;

final class BreachPredictor
{
    public function predict(
        SlaClock $clock,
        DateTimeImmutable $at,
        int $estimatedWorkMinutes,
        int $queueDelayMinutes
    ): BreachPrediction {
        if ($estimatedWorkMinutes < 0 || $queueDelayMinutes < 0) {
            throw new InvalidArgumentException('Estimated work and queue delay must be non-negative.');
        }
        if ($at < $clock->lastMutationAt()) {
            throw new DomainException('Breach prediction cannot use an observation older than the SLA clock state.');
        }

        $remaining = $clock->remainingResolutionWorkingMinutes($at);
        $demand = $estimatedWorkMinutes + $queueDelayMinutes;
        $status = $clock->status($at);
        $reasons = [];

        if ($status === 'completed') {
            return new BreachPrediction('normal', $remaining, $demand, ['Resolved SLA clock requires no further breach prediction.']);
        }
        if ($status === 'paused') {
            return new BreachPrediction(
                'watch',
                $remaining,
                $demand,
                ['SLA is paused under governed evidence; prediction remains on watch until resume recalculates deadlines.']
            );
        }

        if (str_starts_with($status, 'breached_') || $remaining === 0) {
            $risk = 'breach';
            $reasons[] = 'The SLA is already breached or has no remaining working time.';
        } elseif ($demand >= $remaining) {
            $risk = 'high';
            $reasons[] = 'Estimated demand meets or exceeds remaining resolution time.';
        } elseif ($demand >= (int) ceil($remaining * 0.75)) {
            $risk = 'watch';
            $reasons[] = 'Estimated demand consumes at least seventy-five percent of remaining time.';
        } else {
            $risk = 'normal';
            $reasons[] = 'Estimated demand remains within the available working-time budget.';
        }

        if ($clock->policy()->priority() === 'P1' && $risk === 'normal') {
            $risk = 'watch';
            $reasons[] = 'P1 cases remain under active watch even before a projected breach.';
        }

        return new BreachPrediction($risk, $remaining, $demand, $reasons);
    }
}
