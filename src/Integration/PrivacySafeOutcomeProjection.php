<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use InvalidArgumentException;

/**
 * Produces a privacy-thresholded, non-punitive aggregate for File 26.
 * No raw case, person, message, evidence, donation or payment fact may enter it.
 */
final class PrivacySafeOutcomeProjection
{
    /** @var list<string> */
    private const PROHIBITED_FIELDS = [
        'case_id', 'appeal_id', 'user_id', 'requester_reference', 'actor_reference',
        'subject', 'message', 'body', 'note', 'attachment', 'evidence', 'email', 'phone',
        'donor_status', 'donation_amount', 'donor_tier', 'payment_status', 'payment_tier',
    ];

    /** @var list<string> */
    private const FINAL_OUTCOMES = ['uphold', 'modify', 'overturn', 'remand', 'withdraw'];

    /**
     * @param list<array<string,mixed>> $records
     * @return array<string,mixed>
     */
    public function project(array $records, string $policyVersion, int $minimumCohort = 20): array
    {
        if (trim($policyVersion) === '' || $minimumCohort < 10 || $minimumCohort > 10000) {
            throw new InvalidArgumentException('Projection policy version or privacy threshold is invalid.');
        }

        $counts = array_fill_keys(self::FINAL_OUTCOMES, 0);
        $eligible = 0;
        $excludedPending = 0;
        $excludedUnimplemented = 0;

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Outcome projection record is malformed.');
            }
            $this->assertMinimized($record);

            $state = $record['appeal_state'] ?? null;
            $outcome = $record['outcome'] ?? null;
            $implemented = $record['implementation_confirmed'] ?? false;

            if (!is_string($state) || !is_string($outcome) || !is_bool($implemented)) {
                throw new InvalidArgumentException('Outcome projection record is incomplete.');
            }

            if (in_array($state, ['submitted', 'eligibility_review', 'accepted', 'under_review', 'native_decision_pending', 'reopened'], true)) {
                ++$excludedPending;
                continue;
            }
            if (!in_array($outcome, self::FINAL_OUTCOMES, true)) {
                throw new InvalidArgumentException('Outcome projection contains an unknown final outcome.');
            }
            if (!$implemented) {
                ++$excludedUnimplemented;
                continue;
            }

            ++$counts[$outcome];
            ++$eligible;
        }

        $base = [
            'contract_version' => \Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION,
            'policy_version' => trim($policyVersion),
            'privacy_threshold' => $minimumCohort,
            'cohort_size' => $eligible,
            'excluded_pending' => $excludedPending,
            'excluded_unimplemented' => $excludedUnimplemented,
            'appeal_use_penalty' => false,
            'donation_or_payment_signal' => false,
            'raw_case_data_included' => false,
        ];

        if ($eligible < $minimumCohort) {
            return $base + [
                'eligible_for_ranking_consumption' => false,
                'suppression_reason' => 'below_privacy_threshold',
                'outcomes' => null,
            ];
        }

        $rates = [];
        foreach ($counts as $outcome => $count) {
            $rates[$outcome] = round($count / $eligible, 6);
        }

        return $base + [
            'eligible_for_ranking_consumption' => true,
            'suppression_reason' => null,
            'outcomes' => $counts,
            'rates' => $rates,
        ];
    }

    /** @param array<string,mixed> $record */
    private function assertMinimized(array $record): void
    {
        foreach (self::PROHIBITED_FIELDS as $field) {
            if (array_key_exists($field, $record)) {
                throw new InvalidArgumentException(sprintf('Privacy-safe outcome projection forbids field: %s.', $field));
            }
        }
        $allowed = ['appeal_state', 'outcome', 'implementation_confirmed'];
        foreach (array_keys($record) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Outcome projection field is not allowlisted: %s.', $field));
            }
        }
    }
}
