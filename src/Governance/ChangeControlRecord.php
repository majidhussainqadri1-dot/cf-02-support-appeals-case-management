<?php

declare(strict_types=1);

namespace Sabri\CF02\Governance;

use DateTimeImmutable;

final class ChangeControlRecord
{
    /**
     * @param array<string, mixed> $record
     * @return list<string>
     */
    public static function validate(array $record): array
    {
        $reasons = [];

        foreach ([
            'id',
            'requested_by',
            'recorded_at',
            'old_rule',
            'new_rule',
            'rationale',
            'data_impact',
            'security_privacy_impact',
            'shariah_impact',
            'migration_plan',
            'rollback_plan',
            'test_plan',
            'approval_status',
        ] as $field) {
            if (!isset($record[$field]) || !is_string($record[$field]) || trim($record[$field]) === '') {
                $reasons[] = sprintf('Change-control field is missing: %s.', $field);
            }
        }

        if (isset($record['id']) && is_string($record['id']) && preg_match('/^CF02-CCR-\d{4}$/', $record['id']) !== 1) {
            $reasons[] = 'Change-control ID must use CF02-CCR-0000 format.';
        }

        if (isset($record['recorded_at']) && is_string($record['recorded_at']) && !self::isIso8601($record['recorded_at'])) {
            $reasons[] = 'Change-control timestamp is not valid ISO 8601.';
        }

        if (!isset($record['affected_files']) || !is_array($record['affected_files']) || $record['affected_files'] === []) {
            $reasons[] = 'Change-control affected files are missing.';
        }

        if (!isset($record['requirement_ids']) || !is_array($record['requirement_ids']) || $record['requirement_ids'] === []) {
            $reasons[] = 'Change-control requirement IDs are missing.';
        }

        if (($record['approval_status'] ?? null) === 'approved') {
            foreach (['approved_by', 'approved_at'] as $field) {
                if (!isset($record[$field]) || !is_string($record[$field]) || trim($record[$field]) === '') {
                    $reasons[] = sprintf('Approved change-control field is missing: %s.', $field);
                }
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
