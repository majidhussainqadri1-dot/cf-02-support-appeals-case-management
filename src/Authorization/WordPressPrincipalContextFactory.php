<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Contracts\SupportContractCatalog;

final class WordPressPrincipalContextFactory
{
    public function current(DateTimeImmutable $now): PrincipalContext
    {
        if (!is_user_logged_in()) {
            throw new RuntimeException('Authentication is required.');
        }
        $userId = (int) get_current_user_id();
        $default = $this->defaultAssertion($userId, $now);
        /** @var mixed $filtered */
        $filtered = apply_filters('cf02_file00_authorization_assertion', $default, $userId, $now->format(DATE_ATOM));
        if (!is_array($filtered)) {
            throw new RuntimeException('File 00 authorization assertion is unavailable.');
        }

        $actorReference = (string) ($filtered['actor_ref'] ?? '');
        if (!hash_equals('user:' . $userId, $actorReference)) {
            throw new RuntimeException('File 00 assertion does not match the authenticated WordPress principal.');
        }
        $assertedAt = $this->date((string) ($filtered['asserted_at'] ?? ''));
        $expiresAt = $this->date((string) ($filtered['expires_at'] ?? ''));
        return new PrincipalContext(
            $actorReference,
            $userId,
            is_array($filtered['roles'] ?? null) ? array_values($filtered['roles']) : [],
            is_array($filtered['capabilities'] ?? null) ? array_values($filtered['capabilities']) : [],
            is_array($filtered['represented_requesters'] ?? null) ? array_values($filtered['represented_requesters']) : [],
            (bool) ($filtered['suspended'] ?? true),
            isset($filtered['recent_auth_at']) && is_string($filtered['recent_auth_at']) && $filtered['recent_auth_at'] !== ''
                ? $this->date($filtered['recent_auth_at'])
                : null,
            (string) ($filtered['owner'] ?? ''),
            (string) ($filtered['contract_version'] ?? ''),
            $assertedAt,
            $expiresAt
        );
    }

    /** @return array<string,mixed> */
    private function defaultAssertion(int $userId, DateTimeImmutable $now): array
    {
        $roles = ['user_reporter'];
        $capabilities = SupportContractCatalog::capabilitiesForRole('user_reporter');

        // Deliberately narrow. Institutional/staff/guardian facts must come from File 00.
        return [
            'actor_ref' => 'user:' . $userId,
            'roles' => $roles,
            'capabilities' => $capabilities,
            'represented_requesters' => [],
            'suspended' => false,
            'recent_auth_at' => $now->format(DATE_ATOM),
            'owner' => 'File 00',
            'contract_version' => defined('SMC_CONTRACT_VERSION') ? (string) SMC_CONTRACT_VERSION : '',
            'asserted_at' => $now->format(DATE_ATOM),
            'expires_at' => $now->modify('+5 minutes')->format(DATE_ATOM),
        ];
    }

    private function date(string $value): DateTimeImmutable
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new RuntimeException('Authorization assertion timestamp is invalid.');
        }
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if (!$date instanceof DateTimeImmutable) {
            // DATE_ATOM does not accept fractional seconds on every supported PHP build.
            try {
                $date = new DateTimeImmutable($value);
            } catch (\Throwable) {
                throw new RuntimeException('Authorization assertion timestamp is invalid.');
            }
        }
        return $date;
    }
}
