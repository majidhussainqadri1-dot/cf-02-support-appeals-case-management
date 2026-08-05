<?php

declare(strict_types=1);

namespace Sabri\CF02\Governance;

/**
 * Validates the Founder-approved Islamic institutional due-process envelope.
 * CF-02 coordinates the case and appeal; the relevant native owner executes any
 * final access, role, contract or disciplinary action.
 */
final class InstitutionalDueProcessPolicy
{
    /** @param array<string,mixed> $record @return list<string> */
    public static function validate(array $record): array
    {
        $errors = [];
        $classification = $record['classification'] ?? null;
        $allowed = ['good_faith_inquiry', 'disputed_interpretation', 'alleged_serious_violation'];

        if (!is_string($classification) || !in_array($classification, $allowed, true)) {
            return ['Institutional matter classification is missing or invalid.'];
        }

        try {
            ServiceEqualityPolicy::assertNoPrivilegeSignals($record);
        } catch (\InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        }

        $action = $record['proposed_action'] ?? 'none';
        if (!is_string($action) || !in_array($action, ['none', 'warning', 'access_revocation', 'role_removal', 'contract_termination', 'institutional_expulsion'], true)) {
            $errors[] = 'Institutional proposed action is invalid.';
        }

        if (in_array($classification, ['good_faith_inquiry', 'disputed_interpretation'], true)) {
            if ($action !== 'none') {
                $errors[] = 'Good-faith inquiry or disputed interpretation cannot trigger disciplinary action.';
            }
            return array_values(array_unique($errors));
        }

        foreach (['notice_reference', 'policy_version', 'reviewer_reference', 'appeal_route', 'native_owner_reference'] as $field) {
            if (!isset($record[$field]) || !is_string($record[$field]) || trim($record[$field]) === '') {
                $errors[] = sprintf('Institutional due-process field is required: %s.', $field);
            }
        }

        foreach (['allegations', 'evidence_references'] as $field) {
            $value = $record[$field] ?? null;
            if (!is_array($value) || $value === []) {
                $errors[] = sprintf('Institutional due-process list is required: %s.', $field);
                continue;
            }
            foreach ($value as $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('Institutional due-process list contains an invalid item: %s.', $field);
                    break;
                }
            }
            if (count($value) !== count(array_unique($value, SORT_REGULAR))) {
                $errors[] = sprintf('Institutional due-process list contains duplicates: %s.', $field);
            }
        }

        foreach (['response_opportunity_provided', 'conflict_check_passed', 'proportionality_assessed', 'appeal_available'] as $field) {
            if (($record[$field] ?? false) !== true) {
                $errors[] = sprintf('Institutional due-process safeguard is not confirmed: %s.', $field);
            }
        }

        if ($action !== 'none' && ($record['implementation_verification_required'] ?? false) !== true) {
            $errors[] = 'Disciplinary action requires native-owner implementation verification.';
        }

        if (($record['status'] ?? 'pending') === 'closed'
            && $action !== 'none'
            && (!isset($record['implementation_reference'])
                || !is_string($record['implementation_reference'])
                || trim($record['implementation_reference']) === '')) {
            $errors[] = 'Closed disciplinary matter requires an implementation reference.';
        }

        return array_values(array_unique($errors));
    }
}
