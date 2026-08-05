<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use DateTimeImmutable;
use InvalidArgumentException;
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
                throw new InvalidArgumentException('Assignment candidate list contains an invalid agent profile.');
            }
            if (!$agent->available() || !$agent->supportsQueue($request->queueKey())) {
                continue;
            }
            if (!$agent->hasSkills($request->requiredSkills()) || !$agent->supportsLanguage($request->preferredLanguage())) {
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

            $score = 1200 - (int) round($agent->loadRatio() * 500);
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
                ['No on-duty agent satisfies queue, skill, exact language, capacity, sensitivity and role constraints.'],
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
            $request->sensitive() && $selected->sensitiveClearance(),
            [
                'Selected from agents satisfying queue, skill, exact language, role and capacity constraints.',
                $request->sensitive()
                    ? 'Restricted access is approved for the cleared sensitive liaison assigned to this case.'
                    : 'No restricted projection access is granted by this decision.',
                'Decision expires quickly and must be committed against current capacity state.',
            ],
            $now,
            $now->modify('+5 minutes')
        );
    }
}
