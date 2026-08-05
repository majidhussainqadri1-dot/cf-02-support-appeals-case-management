<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use DateTimeImmutable;
use InvalidArgumentException;

/** Immutable File-00-bound authorization context. */
final class PrincipalContext
{
    /** @var list<string> */ private array $roles;
    /** @var list<string> */ private array $capabilities;
    /** @var list<string> */ private array $representedRequesters;

    /**
     * @param list<string> $roles
     * @param list<string> $capabilities
     * @param list<string> $representedRequesters
     */
    public function __construct(
        private readonly string $actorReference,
        private readonly int $userId,
        array $roles,
        array $capabilities,
        array $representedRequesters,
        private readonly bool $suspended,
        private readonly ?DateTimeImmutable $recentAuthenticationAt,
        private readonly string $assertionOwner,
        private readonly string $assertionVersion,
        private readonly DateTimeImmutable $assertedAt,
        private readonly DateTimeImmutable $expiresAt
    ) {
        if (trim($actorReference) === '' || $userId < 1) {
            throw new InvalidArgumentException('A canonical authenticated actor is required.');
        }
        if ($assertionOwner !== 'File 00' || preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $assertionVersion) !== 1) {
            throw new InvalidArgumentException('Authorization assertion must be owned and versioned by File 00.');
        }
        if ($expiresAt <= $assertedAt || $expiresAt > $assertedAt->modify('+15 minutes')) {
            throw new InvalidArgumentException('Authorization assertion lifetime is invalid.');
        }
        $this->roles = self::normalize($roles, 'role');
        $this->capabilities = self::normalize($capabilities, 'capability');
        $this->representedRequesters = self::normalize($representedRequesters, 'represented requester');
    }

    public function actorReference(): string { return $this->actorReference; }
    public function userId(): int { return $this->userId; }
    public function suspended(): bool { return $this->suspended; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    /** @return list<string> */ public function roles(): array { return $this->roles; }
    /** @return list<string> */ public function capabilities(): array { return $this->capabilities; }
    /** @return list<string> */ public function representedRequesters(): array { return $this->representedRequesters; }

    public function validAt(DateTimeImmutable $at): bool
    {
        return !$this->suspended && $at >= $this->assertedAt && $at < $this->expiresAt;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function hasAnyCapability(string ...$capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if ($this->hasCapability($capability)) {
                return true;
            }
        }
        return false;
    }

    public function represents(string $requesterReference): bool
    {
        return in_array($requesterReference, $this->representedRequesters, true);
    }

    public function recentlyAuthenticated(DateTimeImmutable $at, int $maxAgeSeconds = 900): bool
    {
        if (!$this->recentAuthenticationAt instanceof DateTimeImmutable || $maxAgeSeconds < 1 || $maxAgeSeconds > 3600) {
            return false;
        }
        $age = $at->getTimestamp() - $this->recentAuthenticationAt->getTimestamp();
        return $age >= 0 && $age <= $maxAgeSeconds;
    }

    /** @param list<mixed> $values @return list<string> */
    private static function normalize(array $values, string $label): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Malformed %s list.', $label));
            }
            $normalized[] = trim($value);
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized);
        return $normalized;
    }
}
