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

        self::validateStringList($record, 'affected_files', 'Change-control affected files are missing.', $reasons);
        self::validateStringList($record, 'requirement_ids', 'Change-control requirement IDs are missing.', $reasons);

        if (isset($record['requirement_ids']) && is_array($record['requirement_ids'])) {
            foreach ($record['requirement_ids'] as $requirementId) {
                if (!is_string($requirementId) || preg_match('/^CF02-FR-\d{3}$/', $requirementId) !== 1) {
                    $reasons[] = 'Change-control contains an invalid requirement ID.';
                    break;
                }
            }
        }

        if (($record['approval_status'] ?? null) === 'approved') {
            foreach (['approved_by', 'approved_at'] as $field) {
                if (!isset($record[$field]) || !is_string($record[$field]) || trim($record[$field]) === '') {
                    $reasons[] = sprintf('Approved change-control field is missing: %s.', $field);
                }
            }

            if (isset($record['approved_at']) && is_string($record['approved_at']) && !self::isIso8601($record['approved_at'])) {
                $reasons[] = 'Approved change-control timestamp is not valid ISO 8601.';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $reasons
     */
    private static function validateStringList(array $record, string $field, string $message, array &$reasons): void
    {
        $value = $record[$field] ?? null;
        if (!is_array($value) || $value === []) {
            $reasons[] = $message;
            return;
        }

        if (count(array_unique($value, SORT_REGULAR)) !== count($value)) {
            $reasons[] = sprintf('Change-control field contains duplicate values: %s.', $field);
        }

        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                $reasons[] = sprintf('Change-control field contains an invalid value: %s.', $field);
                break;
            }
        }
    }

    private static function isIso8601(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
