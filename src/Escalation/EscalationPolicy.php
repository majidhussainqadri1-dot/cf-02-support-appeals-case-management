<?php

declare(strict_types=1);

namespace Sabri\CF02\Escalation;

use InvalidArgumentException;

final class EscalationPolicy
{
    public function decide(
        string $priority,
        bool $assigned,
        bool $sensitive,
        BreachPrediction $prediction,
        int $transferCount,
        bool $updateStale
    ): EscalationDecision {
        if (!in_array($priority, ['P1', 'P2', 'P3', 'P4'], true) || $transferCount < 0) {
            throw new InvalidArgumentException('Invalid escalation inputs.');
        }

        $actions = [];
        $reasons = [];
        $level = 'none';

        if (!$assigned) {
            $level = 'team_lead';
            $actions[] = 'Require accountable owner assignment.';
            $reasons[] = 'Case has no accountable owner.';
        }

        if ($sensitive) {
            $level = self::maxLevel($level, 'specialist');
            $actions[] = 'Route to an approved purpose-bound sensitive liaison.';
            $reasons[] = 'Sensitive case requires restricted specialist handling.';
        }

        if ($transferCount >= 2) {
            $level = self::maxLevel($level, 'team_lead');
            $actions[] = 'Review repeated transfers and ownership fit.';
            $reasons[] = 'Repeated transfer threshold has been reached.';
        }

        if ($updateStale) {
            $level = self::maxLevel($level, 'team_lead');
            $actions[] = 'Require an evidence-based user update.';
            $reasons[] = 'Case update cadence is stale.';
        }

        if ($prediction->risk() === 'watch') {
            $level = self::maxLevel($level, 'watch');
            $actions[] = 'Place case on proactive SLA watch.';
            $reasons[] = 'Breach prediction is at watch level.';
        } elseif ($prediction->risk() === 'high') {
            $level = self::maxLevel($level, 'specialist');
            $actions[] = 'Escalate capacity or specialist support before breach.';
            $reasons[] = 'Breach prediction is high.';
        } elseif ($prediction->risk() === 'breach') {
            $level = self::maxLevel($level, $priority === 'P1' ? 'incident_command' : 'specialist');
            $actions[] = 'Record breach, notify accountable leadership and preserve evidence.';
            $reasons[] = 'SLA breach is actual or unavoidable under current demand.';
        }

        if ($priority === 'P1') {
            $level = self::maxLevel($level, 'specialist');
            $actions[] = 'Maintain immediate human specialist oversight and emergency-boundary messaging.';
            $reasons[] = 'P1 priority cannot be silently downgraded.';
        }

        if ($actions === []) {
            $actions[] = 'Continue accountable handling under the active SLA policy.';
            $reasons[] = 'No escalation threshold is currently met.';
        }

        return new EscalationDecision(
            $level,
            array_values(array_unique($actions)),
            array_values(array_unique($reasons)),
            $level !== 'none'
        );
    }

    private static function maxLevel(string $left, string $right): string
    {
        $rank = ['none' => 0, 'watch' => 1, 'team_lead' => 2, 'specialist' => 3, 'incident_command' => 4];
        return $rank[$right] > $rank[$left] ? $right : $left;
    }
}
