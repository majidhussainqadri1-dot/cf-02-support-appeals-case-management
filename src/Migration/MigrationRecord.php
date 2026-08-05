<?php

declare(strict_types=1);

namespace Sabri\CF02\Migration;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class MigrationRecord
{
    public function __construct(
        private readonly string $sourceOwner,
        private readonly string $sourceRecordId,
        private readonly int $sourceVersion,
        private readonly SupportCaseId $targetCaseId,
        private readonly string $sourceHash,
        private readonly string $targetHash,
        private readonly string $status,
        private readonly DateTimeImmutable $recordedAt,
        private readonly ?string $failureCode = null
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $sourceOwner) !== 1 || trim($sourceRecordId) === '' || $sourceVersion < 1) {
            throw new InvalidArgumentException('Invalid migration source identity.');
        }
        foreach ([$sourceHash, $targetHash] as $hash) {
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Invalid migration integrity hash.');
            }
        }
        if (!in_array($status, ['shadowed', 'migrated', 'reconciled', 'failed', 'rolled_back'], true)) {
            throw new InvalidArgumentException('Invalid migration status.');
        }
        if ($status === 'failed' && trim((string) $failureCode) === '') {
            throw new InvalidArgumentException('Failed migration requires a failure code.');
        }
    }

    public function mappingKey(): string { return $this->sourceOwner . ':' . $this->sourceRecordId; }
    public function sourceOwner(): string { return $this->sourceOwner; }
    public function sourceRecordId(): string { return $this->sourceRecordId; }
    public function sourceVersion(): int { return $this->sourceVersion; }
    public function targetCaseId(): SupportCaseId { return $this->targetCaseId; }
    public function sourceHash(): string { return $this->sourceHash; }
    public function targetHash(): string { return $this->targetHash; }
    public function status(): string { return $this->status; }
    public function recordedAt(): DateTimeImmutable { return $this->recordedAt; }
    public function failureCode(): ?string { return $this->failureCode; }
}
