<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use Sabri\CF02\Contracts\RequiredCompanionContracts;
use Sabri\CF02\Infrastructure\WordPress\SchemaCompletion;

final class WordPressActivationEvidence implements ActivationEvidence
{
    public function runtimeSwitchEnabled(): bool
    {
        return defined('CF02_RUNTIME_ENABLED') && CF02_RUNTIME_ENABLED === true;
    }

    public function runtimeEnvironment(): string
    {
        $environment = defined('CF02_RUNTIME_ENVIRONMENT') ? strtolower((string) CF02_RUNTIME_ENVIRONMENT) : 'production';
        return in_array($environment, ['staging', 'production'], true) ? $environment : 'invalid';
    }

    public function exactRuntimeIdentity(): array
    {
        $defaults = [
            'runtime_version' => defined('CF02_VERSION') ? (string) CF02_VERSION : '',
            'schema_version' => SchemaCompletion::VERSION,
            'source_sha' => '',
            'package_sha256' => '',
        ];
        /** @var mixed $filtered */
        $filtered = apply_filters('cf02_exact_runtime_identity', $defaults);
        return is_array($filtered) ? array_merge($defaults, array_intersect_key($filtered, $defaults)) : $defaults;
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
                'enabled' => $definition['required'],
                'owner' => $definition['owner'],
                'contract_version' => null,
                'capabilities' => [],
                'health' => 'unknown',
                'health_checked_at' => null,
            ];
        }

        // File 00 runtime function presence and a version constant are only
        // discovery hints, never fresh native-owner health attestation.
        // Keep the mandatory dependency blocked until the actual File 00
        // provider supplies a verified, scoped, fresh manifest via filter.

        /** @var mixed $filtered */
        $filtered = apply_filters('cf02_dependency_contract_evidence', $defaults);
        return is_array($filtered) ? $filtered : $defaults;
    }

    public function operationalEvidence(): array
    {
        $value = get_option('cf02_operational_activation_evidence', []);
        return is_array($value) ? $value : [];
    }
}
