<?php

declare(strict_types=1);

namespace Sabri\CF02\Staffing;

final class StaffingRegistry
{
    /** @return array<string, list<string>> */
    public static function capabilities(): array
    {
        return [
            StaffingRole::SupportAgent->value => ['view_assigned_case', 'reply_to_case', 'manage_case_tasks'],
            StaffingRole::SpecialistAgent->value => ['view_assigned_case', 'diagnose_product_issue', 'request_native_owner_action'],
            StaffingRole::TeamLead->value => ['assign_case', 'manage_sla_escalation', 'review_quality_sample', 'bounded_bulk_action'],
            StaffingRole::AppealReviewer->value => ['review_appeal_dossier', 'issue_reasoned_appeal_outcome'],
            StaffingRole::SensitiveLiaison->value => ['view_approved_sensitive_projection', 'request_native_specialist_action'],
            StaffingRole::Auditor->value => ['view_sampled_read_only_evidence', 'view_minimized_metrics'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function prohibitions(): array
    {
        return [
            StaffingRole::SupportAgent->value => ['read_secrets', 'take_over_account', 'make_native_final_decision'],
            StaffingRole::SpecialistAgent->value => ['write_foreign_domain_directly', 'read_unrelated_records'],
            StaffingRole::TeamLead->value => ['decide_own_prior_appeal'],
            StaffingRole::AppealReviewer->value => ['review_conflicted_case', 'blanket_sensitive_access'],
            StaffingRole::SensitiveLiaison->value => ['expose_sensitive_data_to_ordinary_agent'],
            StaffingRole::Auditor->value => ['mutate_case', 'read_credentials', 'read_full_private_content_by_default'],
        ];
    }

    /**
     * @param array{requester:string, original_decider:string, reviewer:string, executor:string, reconciler:string, auditor:string} $actors
     * @return list<string>
     */
    public static function validateHighRiskSeparation(array $actors): array
    {
        $reasons = [];

        foreach (['requester', 'original_decider', 'reviewer', 'executor', 'reconciler', 'auditor'] as $key) {
            if (!isset($actors[$key]) || trim($actors[$key]) === '') {
                $reasons[] = sprintf('High-risk assignment actor is missing: %s.', $key);
            }
        }

        if (($actors['reviewer'] ?? null) === ($actors['original_decider'] ?? null)) {
            $reasons[] = 'Appeal reviewer cannot be the original decision maker.';
        }

        if (($actors['reviewer'] ?? null) === ($actors['executor'] ?? null)) {
            $reasons[] = 'Appeal reviewer cannot also execute the native-domain action.';
        }

        if (($actors['auditor'] ?? null) === ($actors['executor'] ?? null)) {
            $reasons[] = 'Auditor cannot execute the action being audited.';
        }

        if (($actors['requester'] ?? null) === ($actors['reviewer'] ?? null)) {
            $reasons[] = 'Requester cannot review the same high-risk action.';
        }

        return array_values(array_unique($reasons));
    }
}
