<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

/**
 * CF-02 does not own platform identities, roles or capabilities.
 *
 * Older release candidates created local WordPress roles. They are removed as
 * legacy duplicate authority. Current authorization comes only from the
 * action-time File 00 assertion.
 */
final class RoleRegistrar
{
    /** @var list<string> */
    private const LEGACY_ROLES = [
        'cf02_support_agent',
        'cf02_specialist_agent',
        'cf02_team_lead',
        'cf02_appeal_reviewer',
        'cf02_liaison',
        'cf02_auditor',
        'cf02_support_manager',
    ];

    public static function register(): void
    {
        foreach (self::LEGACY_ROLES as $role) {
            if (get_role($role) !== null) {
                remove_role($role);
            }
        }
        update_option('cf02_role_authority_mode', 'file00_only', false);
    }

    /** Kept only for compatibility with pre-1.0 integrations; never grants authority. */
    public static function wpCapability(string $contractCapability): string
    {
        return 'cf02_' . str_replace('.', '_', $contractCapability);
    }
}
