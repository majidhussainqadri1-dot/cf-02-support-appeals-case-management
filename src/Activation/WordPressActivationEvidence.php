<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use Sabri\CF02\Contracts\RequiredCompanionContracts;

final class WordPressActivationEvidence implements ActivationEvidence
{
    public function runtimeSwitchEnabled(): bool
    {
        return defined('CF02_RUNTIME_ENABLED') && CF02_RUNTIME_ENABLED === true;
    }

    public function founderApproval(): array
    {
        $value = get_option('cf02_founder_activation_approval', []);
        return is_array($value) ? $value : [];
    }

    public function dependencyReadiness(): array
    {
        $defaults = [];

        foreach (RequiredCompanionContracts::definitions() as $key => $definition) {
            $defaults[$key] = [
                'ready' => false,
                'owner' => $definition['owner'],
                'contract_version' => null,
                'capabilities' => [],
            ];
        }

        $membershipVersion = defined('SMC_CONTRACT_VERSION')
            ? (string) constant('SMC_CONTRACT_VERSION')
            : null;

        $defaults['file_00_membership_contract'] = [
            'ready' => function_exists('smc_membership_assertions') && $membershipVersion !== null,
            'owner' => 'File 00',
            'contract_version' => $membershipVersion,
            'capabilities' => function_exists('smc_membership_assertions') ? ['identity_assertions'] : [],
        ];

        /**
         * Companion modules may provide versioned readiness evidence.
         * Hook or plugin presence alone must never be treated as readiness.
         *
         * @param array<string, array<string, mixed>> $defaults
         */
        $filtered = apply_filters('cf02_dependency_contract_evidence', $defaults);
        return is_array($filtered) ? $filtered : $defaults;
    }

    public function operationalEvidence(): array
    {
        $value = get_option('cf02_operational_activation_evidence', []);
        return is_array($value) ? $value : [];
    }
}
