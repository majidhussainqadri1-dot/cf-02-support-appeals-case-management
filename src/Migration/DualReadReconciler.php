<?php

declare(strict_types=1);

namespace Sabri\CF02\Migration;

use InvalidArgumentException;

final class DualReadReconciler
{
    /**
     * @param array<string, string|int|bool|null> $sourceProjection
     * @param array<string, string|int|bool|null> $targetProjection
     * @return array{status:string,divergences:array<string,array{source:mixed,target:mixed}>,source_hash:string,target_hash:string}
     */
    public function compare(array $sourceProjection, array $targetProjection): array
    {
        if ($sourceProjection === [] || $targetProjection === []) {
            throw new InvalidArgumentException('Dual-read projections must be non-empty.');
        }
        ksort($sourceProjection);
        ksort($targetProjection);
        $fields = array_values(array_unique(array_merge(array_keys($sourceProjection), array_keys($targetProjection))));
        $divergences = [];
        foreach ($fields as $field) {
            $source = $sourceProjection[$field] ?? null;
            $target = $targetProjection[$field] ?? null;
            if ($source !== $target) {
                $divergences[$field] = ['source' => $source, 'target' => $target];
            }
        }
        $sourceHash = hash('sha256', json_encode($sourceProjection, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $targetHash = hash('sha256', json_encode($targetProjection, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return [
            'status' => $divergences === [] ? 'reconciled' : 'diverged',
            'divergences' => $divergences,
            'source_hash' => $sourceHash,
            'target_hash' => $targetHash,
        ];
    }

    /** @param list<array{status:string,divergences:array<string,mixed>}> $results */
    public function cutoverDecision(
        array $results,
        int $expectedRecords,
        int $migratedRecords,
        int $unexplainedSlaDivergences,
        int $unexplainedAppealDivergences,
        bool $rollbackRehearsed
    ): array {
        if ($expectedRecords < 0 || $migratedRecords < 0 || $migratedRecords > $expectedRecords
            || $unexplainedSlaDivergences < 0 || $unexplainedAppealDivergences < 0) {
            throw new InvalidArgumentException('Invalid cutover counts.');
        }
        $diverged = count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') !== 'reconciled'));
        $allowed = $expectedRecords === $migratedRecords
            && $diverged === 0
            && $unexplainedSlaDivergences === 0
            && $unexplainedAppealDivergences === 0
            && $rollbackRehearsed;
        return [
            'allowed' => $allowed,
            'expected_records' => $expectedRecords,
            'migrated_records' => $migratedRecords,
            'diverged_records' => $diverged,
            'unexplained_sla_divergences' => $unexplainedSlaDivergences,
            'unexplained_appeal_divergences' => $unexplainedAppealDivergences,
            'rollback_rehearsed' => $rollbackRehearsed,
            'reason' => $allowed
                ? 'Zero unexplained record, SLA and appeal divergence with rehearsed rollback.'
                : 'Cutover remains blocked until migration, reconciliation and rollback evidence are complete.',
        ];
    }
}
