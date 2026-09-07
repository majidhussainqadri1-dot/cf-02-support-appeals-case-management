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
        $fields = $request->fields();
        ServiceEqualityPolicy::assertNoPrivilegeSignals($fields);
        $category = SupportTaxonomy::defaults()[$request->categoryKey()];

        $harm = strtolower(trim((string) ($fields['harm_level'] ?? ''));
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
            $request->urgency() === 'immediate',
            $request->impact() === 'critical',
            $deadline instanceof DateTimeImmutable && $deadline <= $now->modify('+4 hours') => 'P1',
            $harm === 'high',
            $request->urgency() === 'urgent',
            $request->impact() === 'high',
            $deadline instanceof DateTimeImmutable && $deadline <= $now->modify('+24 hours') => 'P2',
            $request->impact() === 'medium' || $harm === 'medium' => 'P3',
            default => 'P4',
        };

        $severity = match (true) {
            $harm === 'critical' || $request->impact() === 'critical' => 'S1',
            $harm === 'high' || $request->impact() === 'high' => 'S2',
            $harm === 'medium' || $request->impact() === 'medium' => 'S3',
            default => 'S4',
        };

        $specialistRequired = $category->specialistOnly()
            || $competence !== ''
            || in_array($request->categoryKey(), ['account_access', 'verification', 'clinic_appointment', 'messages_calls'], true);

        $indicator = strtolower(trim((string) ($fields['safety_indicator'] ?? $fields['immediacy'] ?? '')));
        $explicitAcuteIndicator = in_array($indicator, ['immediate', 'emergency', 'acute_danger', 'danger_now'], true);
        $emergencyDiversionRequired = $explicitAcuteIndicator
            || $harm === 'critical'
            || ($request->urgency() === 'immediate'
                && in_array($request->categoryKey(), ['clinic_appointment', 'messages_calls', 'safety_abuse'], true));

        $humanReviewRequired = !$request->senderVerified()
            || $priority === 'P1'
            || $specialistRequired
            || $emergencyDiversionRequired
            || $request->accessibilityNeeds() !== [];

        $reasons = [sprintf('Category routes to queue %s.', $category->queueKey())];
        if (!$request->senderVerified()) {
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
