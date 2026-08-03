<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseAssignment
{
    private int $version = 1;
    private ?string $ownerReference = null;
    /** @var array<string, array{scopes:list<string>, expires_at:DateTimeImmutable}> */
    private array $collaborators = [];
    /** @var list<array<string, string>> */
    private array $history = [];

    public function __construct(private readonly SupportCaseId $caseId)
    {
    }

    public function assign(AssignmentDecision $decision, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertDecision($decision);
        if ($this->ownerReference !== null) {
            throw new DomainException('Case already has an accountable owner; use transfer().');
        }

        $this->ownerReference = $decision->agentReference();
        $this->history[] = [
            'type' => 'assigned',
            'agent' => (string) $this->ownerReference,
            'at' => $decision->decidedAt()->format(DATE_ATOM),
        ];
        ++$this->version;
    }

    public function transfer(
        AssignmentDecision $decision,
        string $reason,
        int $expectedVersion,
        ?DateTimeImmutable $transferredAt = null
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertDecision($decision);
        $transferredAt ??= new DateTimeImmutable('now');

        if ($this->ownerReference === null) {
            throw new DomainException('An unowned case must be assigned before transfer.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Transfer reason is required.');
        }
        if (hash_equals($this->ownerReference, (string) $decision->agentReference())) {
            throw new DomainException('Transfer target is already the accountable owner.');
        }

        $previous = $this->ownerReference;
        unset($this->collaborators[$previous]);
        $this->ownerReference = $decision->agentReference();
        unset($this->collaborators[(string) $this->ownerReference]);

        $this->history[] = [
            'type' => 'transferred',
            'from' => $previous,
            'to' => (string) $this->ownerReference,
            'reason' => trim($reason),
            'at' => $transferredAt->format(DATE_ATOM),
        ];
        ++$this->version;
    }

    /** @param list<string> $scopes */
    public function addCollaborator(
        string $agentReference,
        array $scopes,
        DateTimeImmutable $expiresAt,
        int $expectedVersion,
        ?DateTimeImmutable $now = null
    ): void {
        $this->assertVersion($expectedVersion);
        $now ??= new DateTimeImmutable('now');
        $agentReference = trim($agentReference);

        if ($agentReference === '' || $this->ownerReference === null) {
            throw new InvalidArgumentException('Collaborator and accountable owner are required.');
        }
        if (hash_equals($agentReference, $this->ownerReference)) {
            throw new DomainException('Accountable owner cannot also be recorded as a collaborator.');
        }
        self::assertScopes($scopes);
        if ($expiresAt <= $now) {
            throw new InvalidArgumentException('Collaborator access must have a future expiry.');
        }

        $this->collaborators[$agentReference] = ['scopes' => $scopes, 'expires_at' => $expiresAt];
        $this->history[] = [
            'type' => 'collaborator_added',
            'agent' => $agentReference,
            'scopes' => implode(',', $scopes),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'at' => $now->format(DATE_ATOM),
        ];
        ++$this->version;
    }

    public function revokeCollaborator(string $agentReference, string $reason, int $expectedVersion, ?DateTimeImmutable $now = null): bool
    {
        $this->assertVersion($expectedVersion);
        $now ??= new DateTimeImmutable('now');
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Collaboration revocation reason is required.');
        }
        if (!isset($this->collaborators[$agentReference])) {
            return false;
        }

        unset($this->collaborators[$agentReference]);
        $this->history[] = [
            'type' => 'collaborator_revoked',
            'agent' => $agentReference,
            'reason' => trim($reason),
            'at' => $now->format(DATE_ATOM),
        ];
        ++$this->version;
        return true;
    }

    public function canAccess(string $agentReference, string $scope, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable('now');
        if ($this->ownerReference !== null && hash_equals($this->ownerReference, $agentReference)) {
            return true;
        }
        $grant = $this->collaborators[$agentReference] ?? null;
        if ($grant === null || $grant['expires_at'] <= $now) {
            return false;
        }
        return in_array($scope, $grant['scopes'], true);
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function version(): int { return $this->version; }
    public function ownerReference(): ?string { return $this->ownerReference; }
    /** @return list<array<string, string>> */ public function history(): array { return $this->history; }

    private function assertDecision(AssignmentDecision $decision): void
    {
        if (!$decision->caseId()->equals($this->caseId) || !$decision->isAssigned()) {
            throw new DomainException('Assignment decision is unassigned or belongs to another case.');
        }
    }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale assignment version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }

    /** @param list<string> $scopes */
    private static function assertScopes(array $scopes): void
    {
        $allowed = ['view_case', 'reply_case', 'manage_tasks', 'restricted_projection'];
        if ($scopes === []) {
            throw new InvalidArgumentException('At least one collaboration scope is required.');
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, $allowed, true)) {
                throw new InvalidArgumentException('Invalid collaboration scope.');
            }
        }
        if (count($scopes) !== count(array_unique($scopes))) {
            throw new InvalidArgumentException('Duplicate collaboration scopes are prohibited.');
        }
    }
}
