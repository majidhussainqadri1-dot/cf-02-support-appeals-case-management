<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Contracts\SupportContractCatalog;

/** Registers operational roles without granting blanket administrator access. */
final class RoleRegistrar
{
    /** @var array<string,array{label:string,contract_role:string}> */
    private const ROLES = [
        'cf02_support_agent' => ['label' => 'CF-02 Support Agent', 'contract_role' => 'support_agent'],
        'cf02_specialist_agent' => ['label' => 'CF-02 Specialist Agent', 'contract_role' => 'specialist_agent'],
        'cf02_team_lead' => ['label' => 'CF-02 Team Lead', 'contract_role' => 'team_lead'],
        'cf02_appeal_reviewer' => ['label' => 'CF-02 Appeal Reviewer', 'contract_role' => 'appeal_reviewer'],
        'cf02_liaison' => ['label' => 'CF-02 Privacy/Security/Clinical Liaison', 'contract_role' => 'privacy_security_clinical_liaison'],
        'cf02_auditor' => ['label' => 'CF-02 Auditor', 'contract_role' => 'auditor'],
        'cf02_support_manager' => ['label' => 'CF-02 Support Manager', 'contract_role' => 'support_manager'],
    ];

    public static function register(): void
    {
        foreach (self::ROLES as $key => $definition) {
            $caps = ['read' => true, 'cf02_support_workspace' => true];
            foreach (SupportContractCatalog::capabilitiesForRole($definition['contract_role']) as $capability) {
                $caps[self::wpCapability($capability)] = true;
            }
            $role = get_role($key);
            if ($role === null) {
                add_role($key, __($definition['label'], 'cf-02-support-appeals-case-management'), $caps);
                continue;
            }
            foreach ($caps as $capability => $granted) {
                if ($granted) {
                    $role->add_cap($capability);
                }
            }
        }
    }

    public static function wpCapability(string $contractCapability): string
    {
        return 'cf02_' . str_replace('.', '_', $contractCapability);
    }
}
