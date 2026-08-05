<?php

declare(strict_types=1);

namespace Sabri\CF02\Resilience;

use DateTimeImmutable;
use InvalidArgumentException;

final class RecoveryEvidence
{
    /** @param list<string> $verifiedScopes */
    public function __construct(
        private readonly string $evidenceId,
        private readonly DateTimeImmutable $performedAt,
        private readonly int $rpoSeconds,
        private readonly int $rtoSeconds,
        private readonly array $verifiedScopes,
        private readonly bool $authorizationRechecked,
        private readonly bool $deletionLedgerReapplied,
        private readonly bool $downstreamReconciled,
        private readonly bool $rollbackRehearsed,
        private readonly string $artifactHash
    ) {
        if (preg_match('/^CF02-REC-[A-F0-9]{20}$/', $evidenceId) !== 1 || $rpoSeconds < 0 || $rtoSeconds < 0) {
            throw new InvalidArgumentException('Invalid recovery evidence identity or objectives.');
        }
        $required = ['database', 'object_storage', 'configuration', 'queues', 'audit', 'provider_mapping'];
        foreach ($required as $scope) {
            if (!in_array($scope, $verifiedScopes, true)) {
                throw new InvalidArgumentException('Recovery evidence is missing a required scope.');
            }
        }
        if (count($verifiedScopes) !== count(array_unique($verifiedScopes))) {
            throw new InvalidArgumentException('Duplicate recovery scopes are prohibited.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $artifactHash) !== 1) {
            throw new InvalidArgumentException('Invalid recovery artifact hash.');
        }
    }

    /** @param list<string> $verifiedScopes */
    public static function create(
        DateTimeImmutable $performedAt,
        int $rpoSeconds,
        int $rtoSeconds,
        array $verifiedScopes,
        bool $authorizationRechecked,
        bool $deletionLedgerReapplied,
        bool $downstreamReconciled,
        bool $rollbackRehearsed,
        string $artifactContent
    ): self {
        return new self(
            'CF02-REC-' . strtoupper(bin2hex(random_bytes(10))),
            $performedAt,
            $rpoSeconds,
            $rtoSeconds,
            $verifiedScopes,
            $authorizationRechecked,
            $deletionLedgerReapplied,
            $downstreamReconciled,
            $rollbackRehearsed,
            hash('sha256', $artifactContent)
        );
    }

    public function passed(): bool
    {
        return $this->authorizationRechecked
            && $this->deletionLedgerReapplied
            && $this->downstreamReconciled
            && $this->rollbackRehearsed;
    }

    public function evidenceId(): string { return $this->evidenceId; }
    public function performedAt(): DateTimeImmutable { return $this->performedAt; }
    public function rpoSeconds(): int { return $this->rpoSeconds; }
    public function rtoSeconds(): int { return $this->rtoSeconds; }
    public function artifactHash(): string { return $this->artifactHash; }
}
