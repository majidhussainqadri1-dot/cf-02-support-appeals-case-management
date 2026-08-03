<?php

declare(strict_types=1);

namespace Sabri\CF02\Migration;

use DomainException;

final class MigrationLedger
{
    /** @var array<string, MigrationRecord> */
    private array $records = [];

    public function record(MigrationRecord $record): MigrationRecord
    {
        $key = $record->mappingKey();
        $existing = $this->records[$key] ?? null;
        if ($existing !== null) {
            if ($existing->sourceVersion() !== $record->sourceVersion()
                || !hash_equals($existing->sourceHash(), $record->sourceHash())
                || !$existing->targetCaseId()->equals($record->targetCaseId())) {
                throw new DomainException('Migration mapping key was reused for a different source or target.');
            }
            return $existing;
        }
        $this->records[$key] = $record;
        return $record;
    }

    public function get(string $sourceOwner, string $sourceRecordId): ?MigrationRecord
    {
        return $this->records[$sourceOwner . ':' . $sourceRecordId] ?? null;
    }

    /** @return list<MigrationRecord> */
    public function failures(): array
    {
        return array_values(array_filter($this->records, static fn (MigrationRecord $record): bool => $record->status() === 'failed'));
    }

    public function count(): int { return count($this->records); }
}
