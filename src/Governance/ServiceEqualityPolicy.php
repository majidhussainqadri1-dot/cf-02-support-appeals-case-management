<?php

declare(strict_types=1);

namespace Sabri\CF02\Governance;

use InvalidArgumentException;

/**
 * Enforces the single-free-tier and donation non-privilege law.
 *
 * Donation, sponsorship, paid promotion, popularity or ranking facts are not
 * valid support-priority, SLA, assignment, review, appeal or quality signals.
 */
final class ServiceEqualityPolicy
{
    /** @var list<string> */
    private const PROHIBITED_PRIVILEGE_FIELDS = [
        'donor_status',
        'donation_amount',
        'donor_tier',
        'sponsorship_tier',
        'paid_promotion',
        'payment_tier',
        'founder_favoritism',
        'popularity',
        'popularity_score',
        'rank',
        'ranking_signal',
        'reach',
        'badge',
        'support_priority_override',
    ];

    /** @param array<string,mixed> $context */
    public static function assertNoPrivilegeSignals(array $context): void
    {
        foreach (self::PROHIBITED_PRIVILEGE_FIELDS as $field) {
            if (array_key_exists($field, $context)) {
                throw new InvalidArgumentException(sprintf(
                    'Support priority and appeal processing cannot consume privilege signal: %s.',
                    $field
                ));
            }
        }
    }

    public static function requesterPriority(string $impact, string $urgency): string
    {
        if (!in_array($impact, ['', 'single_action', 'account_blocked', 'many_users'], true)
            || !in_array($urgency, ['', 'normal', 'time_sensitive'], true)) {
            throw new InvalidArgumentException('Impact or urgency is invalid.');
        }

        return $impact === 'account_blocked' && $urgency === 'time_sensitive' ? 'P2' : 'P3';
    }

    /** @return list<string> */
    public static function prohibitedPrivilegeFields(): array
    {
        return self::PROHIBITED_PRIVILEGE_FIELDS;
    }
}
