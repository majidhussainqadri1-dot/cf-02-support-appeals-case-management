<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Governance\ServiceEqualityPolicy;

final class TriagePolicy
{
    public function decide(IntakeRequest $request): TriageDecision
    {
        return $this->decideAt($request, new DateTimeImmutable('now'));
    }

    public function decideAt(IntakeRequest $request, DateTimeImmutable $now): TriageDecision
    {
        return $this->decideSignals(
            $request->categoryKey(),
            $request->impact(),
            $request->urgency(),
            $request->senderVerified(),
            $request->accessibilityNeeds(),
            $request->fields(),
            $now
        );
    }

    /**
     * Canonical runtime triage entrypoint for normalized support intake.
     *
     * @param list<string> $accessibilityNeeds
     * @param array<string,mixed> $fields
     */
    public function decideSignals(
        string $categoryKey,
        string $impact,
        string $urgency,
        bool $senderVerified,
        array $accessibilityNeeds,
        array $fields,
        DateTimeImmutable $now
    ): TriageDecision {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($fields);
        $taxonomy = SupportTaxonomy::defaults();
        if (!isset($taxonomy[$categoryKey])) {
            throw new InvalidArgumentException('Unknown support category.');
        }
        $category = $taxonomy[$categoryKey];

        $impact = match ($impact) {
            '', 'single_action', 'low' => 'low',
            'medium' => 'medium',
            'account_blocked', 'many_users', 'high' => 'high',
            'critical' => 'critical',
            default => throw new InvalidArgumentException('Invalid impact value.'),
        };
        $urgency = match ($urgency) {
            '', 'normal' => 'normal',
            'time_sensitive', 'urgent' => 'urgent',
            'immediate' => 'immediate',
            default => throw new InvalidArgumentException('Invalid urgency value.'),
        };

        $harm = strtolower(trim((string) ($fields['harm_level'] ?? '')));
        if ($harm !== '' && !in_array($harm, ['none', 'low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Harm level is invalid.');
        }

        $deadline = null;
        $deadlineRaw = trim((string) ($fields['deadline_at'] ?? ''));
        if ($deadlineRaw !== '') {
            try {
                $deadline = new DateTimeImmutable($deadlineRaw);
            } catch (\Throwable) {
                throw new InvalidArgumentException('Case deadline is invalid.');
            }
        }

        $competence = trim((string) ($fields['domain_competence'] ?? ''));
        if ($competence !== '' && preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $competence) !== 1) {
            throw new InvalidArgumentException('Domain competence token is invalid.');
        }

        $priority = match (true) {
            $harm === 'critical',
            $urgency === 'immediate',
            $impact === 'critical',
            $deadline instanceof DateTimeImmutable && $deadline <= $now->modify('+4 hours') => 'P1',
            $harm === 'high',
            $urgency === 'urgent',
            $impact === 'high',
            $deadline instanceof DateTimeImmutable && $deadline <= $now->modify('+24 hours') => 'P2',
            $impact === 'medium' || $harm === 'medium' => 'P3',
            default => 'P4',
        };

        $severity = match (true) {
            $harm === 'critical' || $impact === 'critical' => 'S1',
            $harm === 'high' || $impact === 'high' => 'S2',
            $harm === 'medium' || $impact === 'medium' => 'S3',
            default => 'S4',
        };

        $specialistRequired = $category->specialistOnly()
            || $competence !== ''
            || in_array($categoryKey, ['account_access', 'verification', 'clinic_appointment', 'messages_calls'], true);

        $indicator = strtolower(trim((string) ($fields['safety_indicator'] ?? $fields['immediacy'] ?? '')));
        $explicitAcuteIndicator = in_array($indicator, ['immediate', 'emergency', 'acute_danger', 'danger_now'], true);
        $emergencyDiversionRequired = $explicitAcuteIndicator
            || $harm === 'critical'
            || ($urgency === 'immediate'
                && in_array($categoryKey, ['clinic_appointment', 'messages_calls', 'safety_abuse'], true));

        $humanReviewRequired = !$senderVerified
            || $priority === 'P1'
            || $specialistRequired
            || $emergencyDiversionRequired
            || $accessibilityNeeds !== [];

        $reasons = [sprintf('Category routes to queue %s.', $category->queueKey())];
        if (!$senderVerified) {
            $reasons[] = 'Sender trust is unverified; no identity-sensitive action may be taken from the intake alone.';
        }
        if ($harm !== '') {
            $reasons[] = sprintf('Declared harm level %s is included in triage.', $harm);
        }
        if ($deadline instanceof DateTimeImmutable) {
            $reasons[] = sprintf('Declared deadline %s is included in priority assessment.', $deadline->format(DATE_ATOM));
        }
        if ($competence !== '') {
            $reasons[] = sprintf('Domain competence %s is required for assignment.', $competence);
        }
        if ($priority === 'P1') {
            $reasons[] = 'Immediate, critical-harm or near-deadline intake requires human specialist review.';
        }
        if ($emergencyDiversionRequired) {
            $reasons[] = 'Approved local emergency or acute-danger direction is required; ordinary support SLA is not emergency help.';
        }
        if ($category->specialistOnly()) {
            $reasons[] = 'Category is specialist-only and purpose-bound.';
        }

        return new TriageDecision(
            $category->queueKey(),
            $priority,
            $severity,
            $specialistRequired,
            $humanReviewRequired,
            $emergencyDiversionRequired,
            $reasons
        );
    }

}
