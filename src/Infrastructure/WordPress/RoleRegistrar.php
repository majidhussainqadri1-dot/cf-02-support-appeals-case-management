<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

/** Removes legacy duplicate authority while preserving auditable migration evidence. */
final class RoleRegistrar
{
    /** @var list<string> */
    private const LEGACY_ROLES = ['cf02_support_agent','cf02_specialist_agent','cf02_team_lead','cf02_appeal_reviewer','cf02_liaison','cf02_auditor','cf02_support_manager'];

    public static function register(): void
    {
        $evidence = [];
        foreach (self::LEGACY_ROLES as $role) {
            if (get_role($role) === null) { continue; }
            $ids = get_users(['role'=>$role,'fields'=>'ID']);
            $ids = is_array($ids) ? array_map('intval', $ids) : [];
            sort($ids);
            $evidence[$role] = [
                'assigned_user_count'=>count($ids),
                'assigned_user_set_hash'=>hash('sha256', implode(',', $ids)),
                'removed_at'=>gmdate(DATE_ATOM),
            ];
            remove_role($role);
        }
        if ($evidence !== []) {
            update_option('cf02_legacy_role_migration_evidence', $evidence, false);
            do_action('cf02_legacy_roles_removed', $evidence);
        }
        update_option('cf02_role_authority_mode', 'file00_only', false);
    }

    /** Compatibility label only; never checked for authorization. */
    public static function wpCapability(string $contractCapability): string
    {
        return 'cf02_' . str_replace('.', '_', $contractCapability);
    }
}
