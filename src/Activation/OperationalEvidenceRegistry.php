<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use DateTimeImmutable;

final class OperationalEvidenceRegistry
{
    /** @var list<string> */
    private const REQUIRED = [
        'volume_trigger',
        'staffing',
        'privacy_review',
        'security_review',
        'migration_plan',
        'rollback_plan',
        'zero_critical_high_defects',
    ];

    /**
     * @param array<string, mixed> $records
     * @return list<string>
     */
    public static function validate(array $records): array
    {
        $reasons = [];

        foreach (self::REQUIRED as $key) {
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

            if (isset($record['recorded_at']) && is_string($record['recorded_at']) && !self::isIso8601($record['recorded_at'])) {
                $reasons[] = sprintf('Operational evidence timestamp is invalid: %s.', $key);
            }
        }

        $volume = $records['volume_trigger'] ?? null;
        if (is_array($volume) && ($volume['status'] ?? null) === 'accepted') {
            foreach (['measurement_window', 'metric'] as $field) {
                if (!isset($volume[$field]) || !is_string($volume[$field]) || trim($volume[$field]) === '') {
                    $reasons[] = sprintf('Volume-trigger evidence field is missing: %s.', $field);
                }
            }

            if (!isset($volume['threshold']) || !is_numeric($volume['threshold'])) {
                $reasons[] = 'Volume-trigger threshold is missing or non-numeric.';
            }

            if (!isset($volume['observed_value']) || !is_numeric($volume['observed_value'])) {
                $reasons[] = 'Volume-trigger observed value is missing or non-numeric.';
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

            if (!isset($staffing['queue_owners']) || !is_array($staffing['queue_owners']) || $staffing['queue_owners'] === []) {
                $reasons[] = 'Staffing queue owners are not assigned.';
            }

            foreach ([
                'escalation_tree_approved',
                'emergency_diversion_approved',
                'privacy_training_complete',
                'quality_sampling_approved',
            ] as $field) {
                if (($staffing[$field] ?? false) !== true) {
                    $reasons[] = sprintf('Staffing evidence is incomplete: %s.', $field);
                }
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

    private static function isIso8601(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
