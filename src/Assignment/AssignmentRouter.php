<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use DateTimeImmutable;
use Sabri\CF02\Staffing\StaffingRole;

final class AssignmentRouter
{
    /** @param list<AgentProfile> $agents */
    public function decide(AssignmentRequest $request, array $agents, ?DateTimeImmutable $now = null): AssignmentDecision
    {
        $now ??= new DateTimeImmutable('now');
        $eligible = [];

        foreach ($agents as $agent) {
            if (!$agent instanceof AgentProfile) {
                continue;
            }
            if (!$agent->available() || !$agent->supportsQueue($request->queueKey())) {
                continue;
            }
            if (!$agent->hasSkills($request->requiredSkills())) {
                continue;
            }
            if ($request->sensitive() && (!$agent->sensitiveClearance() || $agent->role() !== StaffingRole::SensitiveLiaison)) {
                continue;
            }
            if (!$request->sensitive() && !in_array($agent->role(), [StaffingRole::SupportAgent, StaffingRole::SpecialistAgent], true)) {
                continue;
            }
            if ($request->specialistRequired() && !in_array($agent->role(), [StaffingRole::SpecialistAgent, StaffingRole::SensitiveLiaison], true)) {
                continue;
            }

            $score = 1000 - (int) round($agent->loadRatio() * 500);
            if ($agent->supportsLanguage($request->preferredLanguage())) {
                $score += 200;
            }
            if ($request->priority() === 'P1') {
                $score += 100;
            }
            if ($agent->role() === StaffingRole::SpecialistAgent || $agent->role() === StaffingRole::SensitiveLiaison) {
                $score += 25;
            }

            $eligible[] = ['agent' => $agent, 'score' => $score];
        }

        if ($eligible === []) {
            return AssignmentDecision::unassigned(
                $request->caseId(),
                $request->queueKey(),
                ['No on-duty agent satisfies queue, skill, capacity, language-sensitive and role constraints.'],
                $now
            );
        }

        usort($eligible, static function (array $left, array $right): int {
            $scoreOrder = $right['score'] <=> $left['score'];
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }
            return strcmp($left['agent']->agentReference(), $right['agent']->agentReference());
        });

        /** @var AgentProfile $selected */
        $selected = $eligible[0]['agent'];

        return AssignmentDecision::assigned(
            $request->caseId(),
            $selected->agentReference(),
            $request->queueKey(),
            $eligible[0]['score'],
            count($eligible),
            [
                'Selected from agents satisfying queue, skill, role and capacity constraints.',
                $selected->supportsLanguage($request->preferredLanguage())
                    ? 'Preferred language is supported.'
                    : 'No exact preferred-language match; assignment remains human-reviewable.',
            ],
            $now
        );
    }
}
