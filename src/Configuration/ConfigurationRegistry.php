<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class ConfigurationRegistry
{
    /** @var array<int, ConfigurationSnapshot> */
    private array $versions = [];
    private ?int $activeVersion = null;

    public function stage(ConfigurationSnapshot $snapshot, bool $highRisk): void
    {
        if ($snapshot->status() !== 'staged') {
            throw new InvalidArgumentException('Only staged configuration snapshots may enter the registry.');
        }
        if ($this->versions !== [] && $snapshot->version() !== max(array_keys($this->versions)) + 1) {
            throw new DomainException('Configuration versions must be contiguous.');
        }
        if ($this->versions === [] && $snapshot->version() !== 1) {
            throw new DomainException('First configuration version must be one.');
        }
        $minimumApprovals = $highRisk ? 2 : 1;
        if (count($snapshot->approvers()) < $minimumApprovals) {
            throw new DomainException('Configuration does not have the required independent approvals.');
        }
        if (in_array($snapshot->createdBy(), $snapshot->approvers(), true) && $highRisk) {
            throw new DomainException('High-risk configuration creator cannot satisfy both approvals.');
        }
        $this->versions[$snapshot->version()] = $snapshot;
    }

    public function activate(int $version, DateTimeImmutable $at): ConfigurationSnapshot
    {
        $snapshot = $this->versions[$version] ?? null;
        if ($snapshot === null) {
            throw new DomainException('Staged configuration version was not found.');
        }
        if ($snapshot->createdAt() > $at) {
            throw new DomainException('Configuration activation cannot precede creation.');
        }
        $active = ConfigurationSnapshot::create(
            $snapshot->configurationId(),
            $snapshot->version(),
            'active',
            $snapshot->configuration(),
            $snapshot->approvers(),
            $snapshot->createdBy(),
            $snapshot->createdAt()
        );
        $this->versions[$version] = $active;
        $this->activeVersion = $version;
        return $active;
    }

    public function rollback(int $targetVersion, string $reason, string $actorReference, DateTimeImmutable $at): ConfigurationSnapshot
    {
        if ($this->activeVersion === null || trim($reason) === '' || trim($actorReference) === '') {
            throw new DomainException('Active configuration, rollback reason and actor are required.');
        }
        $target = $this->versions[$targetVersion] ?? null;
        if ($target === null || $targetVersion >= $this->activeVersion) {
            throw new DomainException('Rollback target must be an existing earlier version.');
        }
        $nextVersion = max(array_keys($this->versions)) + 1;
        $rollback = ConfigurationSnapshot::create(
            $target->configurationId(),
            $nextVersion,
            'rolled_back',
            $target->configuration(),
            [$actorReference],
            $actorReference,
            $at
        );
        $this->versions[$nextVersion] = $rollback;
        $this->activeVersion = $nextVersion;
        return $rollback;
    }

    public function active(): ?ConfigurationSnapshot
    {
        return $this->activeVersion === null ? null : $this->versions[$this->activeVersion];
    }

    /** @return list<ConfigurationSnapshot> */
    public function history(): array
    {
        ksort($this->versions);
        return array_values($this->versions);
    }
}
