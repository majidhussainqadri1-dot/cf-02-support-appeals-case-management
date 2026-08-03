<?php

declare(strict_types=1);

namespace Sabri\CF02\Release;

use DateTimeImmutable;
use InvalidArgumentException;

final class ReleaseGate
{
    /**
     * @param array<string, bool> $evidence
     * @return array{allowed:bool,status:string,missing:list<string>,evaluated_at:string}
     */
    public function evaluate(array $evidence, DateTimeImmutable $evaluatedAt): array
    {
        $required = [
            'all_requirements_coded',
            'two_review_rounds_per_part',
            'full_ci_green',
            'dependency_contracts_green',
            'security_privacy_review_passed',
            'accessibility_matrix_passed',
            'load_resilience_passed',
            'migration_reconciled',
            'backup_restore_passed',
            'rollback_rehearsed',
            'staffing_training_ready',
            'staging_accepted',
            'package_parity_verified',
            'founder_exact_version_approved',
            'zero_known_critical_high_defects',
        ];
        foreach ($evidence as $key => $value) {
            if (!is_string($key) || !is_bool($value)) {
                throw new InvalidArgumentException('Release evidence must be a boolean keyed map.');
            }
        }
        $missing = [];
        foreach ($required as $key) {
            if (($evidence[$key] ?? false) !== true) {
                $missing[] = $key;
            }
        }
        return [
            'allowed' => $missing === [],
            'status' => $missing === [] ? 'accepted_release_candidate' : 'blocked',
            'missing' => $missing,
            'evaluated_at' => $evaluatedAt->format(DATE_ATOM),
        ];
    }
}
