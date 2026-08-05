<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use DateTimeImmutable;

final class OperationalEvidenceRegistry
{
    /** @var list<string> */
    private const CORE_REQUIRED = [
        'volume_trigger', 'staffing', 'privacy_review', 'security_review',
        'migration_plan', 'rollback_plan', 'zero_critical_high_defects',
    ];

    /** @var list<string> */
    private const PRODUCTION_REQUIRED = [
        'staging_acceptance', 'provider_acceptance', 'accessibility_review',
        'load_resilience', 'backup_restore_rehearsal', 'rollback_rehearsal',
    ];

    /**
     * @param array<string,mixed> $records
     * @param array{runtime_version:string,schema_version:string,source_sha:string,package_sha256:string} $identity
     * @return list<string>
     */
    public static function validate(array $records, string $environment, array $identity, DateTimeImmutable $now): array
    {
        $reasons = [];
        if (!in_array($environment, ['staging', 'production'], true)) {
            $reasons[] = 'Runtime environment is invalid.';
        }
        $required = array_merge(self::CORE_REQUIRED, $environment === 'production' ? self::PRODUCTION_REQUIRED : []);
        foreach ($required as $key) {
            $record = $records[$key] ?? null;
            if (!is_array($record) || ($record['status'] ?? null) !== 'accepted') {
                $reasons[] = sprintf('Required operational evidence is missing or unaccepted: %s.', $key);
                continue;
            }
            foreach (['evidence_id', 'owner', 'artifact_ref', 'recorded_at'] as $field) {
                if (!isset($record[$field]) || !is_string($record[$field]) || trim($record[$field]) === '') {
                    $reasons[] = sprintf('Operational evidence field is missing for %s: %s.', $key, $field);
                }
            }
            if (isset($record['recorded_at']) && is_string($record['recorded_at'])) {
                $recordedAt = self::date($record['recorded_at']);
                if (!$recordedAt) {
                    $reasons[] = sprintf('Operational evidence timestamp is invalid: %s.', $key);
                } elseif ($recordedAt > $now->modify('+5 minutes')) {
                    $reasons[] = sprintf('Operational evidence timestamp is in the future: %s.', $key);
                }
            }
        }

        $volume = $records['volume_trigger'] ?? null;
        if (is_array($volume) && ($volume['status'] ?? null) === 'accepted') {
            foreach (['measurement_window', 'metric'] as $field) {
                if (!isset($volume[$field]) || !is_string($volume[$field]) || trim($volume[$field]) === '') {
                    $reasons[] = sprintf('Volume-trigger evidence field is missing: %s.', $field);
                }
            }
            $threshold = $volume['threshold'] ?? null;
            $observed = $volume['observed_value'] ?? null;
            if (!is_numeric($threshold) || (float) $threshold <= 0) {
                $reasons[] = 'Volume-trigger threshold is missing, non-numeric or non-positive.';
            }
            if (!is_numeric($observed) || (float) $observed < 0) {
                $reasons[] = 'Volume-trigger observed value is missing, non-numeric or negative.';
            }
            if (is_numeric($threshold) && is_numeric($observed) && (float) $observed < (float) $threshold) {
                $reasons[] = 'Observed volume does not meet the declared extraction threshold.';
            }
            if (($volume['triggered'] ?? false) !== true) {
                $reasons[] = 'Measured extraction trigger has not been satisfied.';
            }
        }

        $staffing = $records['staffing'] ?? null;
        if (is_array($staffing) && ($staffing['status'] ?? null) === 'accepted') {
            if (!isset($staffing['coverage_hours']) || !is_string($staffing['coverage_hours']) || trim($staffing['coverage_hours']) === '') {
                $reasons[] = 'Staffing coverage hours are not defined.';
            }
            $queueOwners = $staffing['queue_owners'] ?? null;
            if (!is_array($queueOwners) || $queueOwners === []) {
                $reasons[] = 'Staffing queue owners are not assigned.';
            } else {
                foreach ($queueOwners as $queue => $owner) {
                    if (!is_string($queue) || trim($queue) === '' || !is_string($owner) || trim($owner) === '') {
                        $reasons[] = 'Staffing queue-owner evidence contains an invalid assignment.';
                        break;
                    }
                }
            }
            foreach (['escalation_tree_approved','emergency_diversion_approved','privacy_training_complete','quality_sampling_approved'] as $field) {
                if (($staffing[$field] ?? false) !== true) {
                    $reasons[] = sprintf('Staffing evidence is incomplete: %s.', $field);
                }
            }
        }

        foreach (self::PRODUCTION_REQUIRED as $gate) {
            $record = $records[$gate] ?? null;
            if (!is_array($record) || ($record['status'] ?? null) !== 'accepted') {
                continue;
            }
            foreach (['runtime_version','schema_version','source_sha','package_sha256'] as $field) {
                if (($record[$field] ?? null) !== $identity[$field]) {
                    $reasons[] = sprintf('Operational evidence is not bound to the exact artifact for %s: %s.', $gate, $field);
                }
            }
            if (($record['passed'] ?? false) !== true) {
                $reasons[] = sprintf('Operational acceptance gate did not pass: %s.', $gate);
            }
        }

        $defects = $records['zero_critical_high_defects'] ?? null;
        if (is_array($defects) && ($defects['status'] ?? null) === 'accepted') {
            if (($defects['critical_open'] ?? null) !== 0 || ($defects['high_open'] ?? null) !== 0) {
                $reasons[] = 'Critical or High defects remain open.';
            }
        }
        return array_values(array_unique($reasons));
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return null;
        }
        try { return new DateTimeImmutable($value); } catch (\Throwable) { return null; }
    }
}
