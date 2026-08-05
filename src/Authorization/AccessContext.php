<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class AccessContext
{
    /**
     * @param list<string> $roles
     * @param list<string> $capabilities
     * @param list<string> $assignedCaseIds
     * @param list<string> $assignedQueueKeys
     */
    public function __construct(
        private readonly string $actorReference,
        private readonly array $roles,
        private readonly array $capabilities,
        private readonly array $assignedCaseIds,
        private readonly array $assignedQueueKeys,
        private readonly string $purpose,
        private readonly DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $expiresAt,
        private readonly ?DateTimeImmutable $recentAuthenticationAt,
        private readonly bool $sensitiveApproval,
        private readonly bool $suspended = false
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{2,127}$/', $actorReference) !== 1) {
            throw new InvalidArgumentException('Invalid access actor reference.');
        }
        if ($purpose === '' || preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $purpose) !== 1) {
            throw new InvalidArgumentException('Invalid access purpose.');
        }
        if ($expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Access context expiry must follow issuance.');
        }
        self::assertUniqueTokens($roles, 'role');
        self::assertUniqueTokens($capabilities, 'capability');
        self::assertUniqueTokens($assignedQueueKeys, 'queue');
        if ($roles === [] || $capabilities === []) {
            throw new InvalidArgumentException('Access context requires at least one role and capability.');
        }
        foreach ($assignedCaseIds as $caseId) {
            if (!is_string($caseId)) {
                throw new InvalidArgumentException('Assigned case identifiers must be strings.');
            }
            SupportCaseId::fromString($caseId);
        }
        if (count($assignedCaseIds) !== count(array_unique($assignedCaseIds))) {
            throw new InvalidArgumentException('Duplicate assigned cases are prohibited.');
        }
        if ($recentAuthenticationAt !== null && $recentAuthenticationAt < $issuedAt->modify('-24 hours')) {
            throw new InvalidArgumentException('Recent-authentication evidence is outside the accepted context window.');
        }
    }

    public function actorReference(): string { return $this->actorReference; }
    public function purpose(): string { return $this->purpose; }
    public function issuedAt(): DateTimeImmutable { return $this->issuedAt; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function sensitiveApproval(): bool { return $this->sensitiveApproval; }
    public function suspended(): bool { return $this->suspended; }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function isAssignedTo(SupportCaseId $caseId): bool
    {
        return in_array($caseId->value(), $this->assignedCaseIds, true);
    }

    public function isAssignedQueue(string $queueKey): bool
    {
        return in_array($queueKey, $this->assignedQueueKeys, true);
    }

    public function isActiveAt(DateTimeImmutable $at): bool
    {
        return !$this->suspended && $at >= $this->issuedAt && $at < $this->expiresAt;
    }

    public function hasRecentAuthentication(DateTimeImmutable $at, int $maxAgeMinutes = 15): bool
    {
        if ($this->recentAuthenticationAt === null || $maxAgeMinutes < 1 || $maxAgeMinutes > 1440) {
            return false;
        }
        return $this->recentAuthenticationAt <= $at
            && $this->recentAuthenticationAt >= $at->modify(sprintf('-%d minutes', $maxAgeMinutes));
    }

    /** @return list<string> */
    public function roles(): array { return $this->roles; }

    /** @return list<string> */
    public function capabilities(): array { return $this->capabilities; }

    /** @param list<string> $values */
    private static function assertUniqueTokens(array $values, string $label): void
    {
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $value) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid %s token.', $label));
            }
        }
        if (count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException(sprintf('Duplicate %s tokens are prohibited.', $label));
        }
    }
}
