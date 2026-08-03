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
        $defaults = [
            'file_00_membership_contract' => function_exists('smc_membership_assertions'),
            'file_20_route_shell_contract' => has_action('cf02_register_route_contract') !== false,
            'file_24_assurance_manifest' => has_filter('cf02_security_assurance_manifest') !== false,
            'file_25_component_contract' => has_filter('cf02_visual_component_contract') !== false,
        ];

        $filtered = apply_filters('cf02_dependency_readiness', $defaults);
        return is_array($filtered) ? $filtered : $defaults;
    }

    public function operationalEvidence(): array
    {
        $value = get_option('cf02_operational_activation_evidence', []);
        return is_array($value) ? $value : [];
    }
}
