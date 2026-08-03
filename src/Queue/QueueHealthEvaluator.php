<?php

declare(strict_types=1);

namespace Sabri\CF02\Queue;

final class QueueHealthEvaluator
{
    public function evaluate(QueueHealthSnapshot $snapshot): QueueHealthDecision
    {
        $status = 'healthy';
        $reasons = [];
        $actions = [];

        if ($snapshot->p1UnassignedCases() > 0) {
            $status = 'critical';
            $reasons[] = 'One or more P1 cases lack an accountable owner.';
            $actions[] = 'Require immediate team-lead assignment and specialist oversight.';
        }
        if ($snapshot->breachedCases() > 0) {
            $status = 'critical';
            $reasons[] = 'The queue contains breached cases.';
            $actions[] = 'Open breach review, preserve evidence and rebalance capacity.';
        }
        if ($snapshot->openCases() > 0 && $snapshot->availableAgents() === 0) {
            $status = 'critical';
            $reasons[] = 'Open cases remain while no agent is available.';
            $actions[] = 'Invoke the approved coverage and escalation tree.';
        }

        $riskRatio = $snapshot->openCases() === 0 ? 0.0 : $snapshot->atRiskCases() / $snapshot->openCases();
        if ($status !== 'critical' && ($riskRatio >= 0.20 || $snapshot->utilization() >= 0.85 || $snapshot->oldestOpenWorkingMinutes() >= 1440)) {
            $status = 'watch';
            if ($riskRatio >= 0.20) {
                $reasons[] = 'At least twenty percent of open cases are at risk.';
                $actions[] = 'Review at-risk cases before the next SLA checkpoint.';
            }
            if ($snapshot->utilization() >= 0.85) {
                $reasons[] = 'Queue utilization is at least eighty-five percent.';
                $actions[] = 'Rebalance workload without bypassing skill or privacy controls.';
            }
            if ($snapshot->oldestOpenWorkingMinutes() >= 1440) {
                $reasons[] = 'Oldest open-case age exceeds one working day.';
                $actions[] = 'Review aging ownership, blockers and communication cadence.';
            }
        }

        if ($reasons === []) {
            $reasons[] = 'Queue metrics are within the current governed thresholds.';
            $actions[] = 'Continue monitored handling under the active policies.';
        }

        return new QueueHealthDecision($status, array_values(array_unique($reasons)), array_values(array_unique($actions)));
    }
}
