<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

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
        $membershipVersion = defined('SMC_CONTRACT_VERSION')
            ? (string) constant('SMC_CONTRACT_VERSION')
            : null;

        $defaults = [
            'file_00_membership_contract' => [
                'ready' => function_exists('smc_membership_assertions') && $membershipVersion !== null,
                'owner' => 'File 00',
                'contract_version' => $membershipVersion,
            ],
            'file_20_route_shell_contract' => [
                'ready' => false,
                'owner' => 'File 20',
                'contract_version' => null,
            ],
            'file_24_assurance_manifest' => [
                'ready' => false,
                'owner' => 'File 24',
                'contract_version' => null,
            ],
            'file_25_component_contract' => [
                'ready' => false,
                'owner' => 'File 25',
                'contract_version' => null,
            ],
        ];

        /**
         * Companion modules may provide versioned readiness evidence.
         * Mere hook availability must not be treated as a healthy contract.
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
