<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use DateTimeImmutable;
use RuntimeException;

/**
 * Builds an action-time principal exclusively from a signed/verified File 00
 * assertion supplied by the canonical identity runtime.
 *
 * CF-02 deliberately has no WordPress-role fallback and never mints a user,
 * guardian, staff, recent-authentication or suspension assertion for itself.
 */
final class WordPressPrincipalContextFactory
{
    public const AUDIENCE = 'cf02-support-appeals';

    public function current(DateTimeImmutable $now): PrincipalContext
    {
        if (!is_user_logged_in()) {
            throw new RuntimeException('Authentication is required.');
        }

        $userId = (int) get_current_user_id();
        /** @var mixed $assertion */
        $assertion = apply_filters(
            'cf02_file00_authorization_assertion',
            null,
            $userId,
            self::AUDIENCE,
            $now->format(DATE_ATOM)
        );

        if (!is_array($assertion) || ($assertion['verified'] ?? false) !== true) {
            throw new RuntimeException('A verified File 00 authorization assertion is required.');
        }

        $actorReference = (string) ($assertion['actor_ref'] ?? '');
        if (!hash_equals('user:' . $userId, $actorReference)) {
            throw new RuntimeException('File 00 assertion does not match the authenticated WordPress principal.');
        }
        if (!hash_equals(self::AUDIENCE, (string) ($assertion['audience'] ?? ''))) {
            throw new RuntimeException('File 00 assertion audience is invalid.');
        }
        if (preg_match('/^SMC-AUTH-[A-Za-z0-9_-]{16,128}$/', (string) ($assertion['assertion_id'] ?? '')) !== 1) {
            throw new RuntimeException('File 00 assertion identity is invalid.');
        }
        if (($assertion['owner'] ?? null) !== 'File 00') {
            throw new RuntimeException('Authorization assertion is not owned by File 00.');
        }

        $assertedAt = $this->date((string) ($assertion['asserted_at'] ?? ''));
        $expiresAt = $this->date((string) ($assertion['expires_at'] ?? ''));
        if ($assertedAt > $now->modify('+30 seconds') || $expiresAt <= $now) {
            throw new RuntimeException('Authorization assertion is not currently valid.');
        }

        $roles = $this->stringList($assertion['roles'] ?? null, 'roles', 32, '/^[a-z][a-z0-9_.-]{1,63}$/');
        $capabilities = $this->stringList($assertion['capabilities'] ?? null, 'capabilities', 256, '/^[a-z][a-z0-9_.-]{1,95}$/');
        $represented = $this->stringList($assertion['represented_requesters'] ?? [], 'represented requesters', 64, '/^(?:user|guardian|representative):[A-Za-z0-9._-]{1,128}$/');

        return new PrincipalContext(
            $actorReference,
            $userId,
            $roles,
            $capabilities,
            $represented,
            (bool) ($assertion['suspended'] ?? true),
            isset($assertion['recent_auth_at']) && is_string($assertion['recent_auth_at']) && $assertion['recent_auth_at'] !== ''
                ? $this->date($assertion['recent_auth_at'])
                : null,
            'File 00',
            (string) ($assertion['contract_version'] ?? ''),
            $assertedAt,
            $expiresAt
        );
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $label, int $maximum, string $pattern): array
    {
        if (!is_array($value) || count($value) > $maximum) {
            throw new RuntimeException(sprintf('File 00 assertion %s are malformed.', $label));
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '' || preg_match($pattern, trim($item)) !== 1) {
                throw new RuntimeException(sprintf('File 00 assertion %s are malformed.', $label));
            }
            $result[] = trim($item);
        }
        return array_values(array_unique($result));
    }

    private function date(string $value): DateTimeImmutable
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new RuntimeException('Authorization assertion timestamp is invalid.');
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new RuntimeException('Authorization assertion timestamp is invalid.');
        }
    }
}
