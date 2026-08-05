<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Governance\ServiceEqualityPolicy;

final class TriagePolicy
{
    public function decide(IntakeRequest $request): TriageDecision
    {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($request->fields());
        $category = SupportTaxonomy::defaults()[$request->categoryKey()];
        $priority = match (true) {
            $request->urgency() === 'immediate', $request->impact() === 'critical' => 'P1',
            $request->urgency() === 'urgent', $request->impact() === 'high' => 'P2',
            $request->impact() === 'medium' => 'P3',
            default => 'P4',
        };

        $severity = match ($request->impact()) {
            'critical' => 'S1',
            'high' => 'S2',
            'medium' => 'S3',
            default => 'S4',
        };

        $specialistRequired = $category->specialistOnly()
            || in_array($request->categoryKey(), ['account_access', 'verification', 'clinic_appointment', 'messages_calls'], true);

        $fields = $request->fields();
        $indicator = strtolower(trim($fields['safety_indicator'] ?? $fields['immediacy'] ?? ''));
        $explicitAcuteIndicator = in_array($indicator, ['immediate', 'emergency', 'acute_danger', 'danger_now'], true);
        $emergencyDiversionRequired = $explicitAcuteIndicator
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
        if ($priority === 'P1') {
            $reasons[] = 'Immediate or critical intake requires human specialist review.';
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
