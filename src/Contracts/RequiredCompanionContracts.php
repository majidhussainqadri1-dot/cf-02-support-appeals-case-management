<?php

declare(strict_types=1);

namespace Sabri\CF02\Contracts;

final class RequiredCompanionContracts
{
    /** @var array<string, array{owner:string, capability:string}> */
    private const DEFINITIONS = [
        'file_00_membership_contract' => [
            'owner' => 'File 00',
            'capability' => 'identity_assertions',
        ],
        'file_09_verification_contract' => [
            'owner' => 'File 09',
            'capability' => 'verification_decision_reference',
        ],
        'file_17_message_report_contract' => [
            'owner' => 'File 17',
            'capability' => 'message_report_decision_reference',
        ],
        'file_18_marketplace_case_contract' => [
            'owner' => 'File 18',
            'capability' => 'listing_decision_reference',
        ],
        'file_20_route_shell_contract' => [
            'owner' => 'File 20',
            'capability' => 'route_shell_mount',
        ],
        'file_21_content_case_contract' => [
            'owner' => 'File 21',
            'capability' => 'content_decision_reference',
        ],
        'file_24_assurance_manifest' => [
            'owner' => 'File 24',
            'capability' => 'assurance_manifest',
        ],
        'file_25_component_contract' => [
            'owner' => 'File 25',
            'capability' => 'component_manifest',
        ],
    ];

    /** @return array<string, array{owner:string, capability:string}> */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @param array<string, mixed> $contracts
     * @return list<string>
     */
    public static function validate(array $contracts): array
    {
        $reasons = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $contract = $contracts[$key] ?? null;

            if (!is_array($contract) || ($contract['ready'] ?? false) !== true) {
                $reasons[] = sprintf('Required dependency contract is not ready: %s.', $key);
                continue;
            }

            if (($contract['owner'] ?? null) !== $definition['owner']) {
                $reasons[] = sprintf('Dependency contract owner mismatch: %s.', $key);
            }

            $version = $contract['contract_version'] ?? null;
            if (!is_string($version) || preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
                $reasons[] = sprintf('Dependency contract version is missing or invalid: %s.', $key);
            }

            $capabilities = $contract['capabilities'] ?? null;
            if (!is_array($capabilities) || !in_array($definition['capability'], $capabilities, true)) {
                $reasons[] = sprintf('Dependency contract capability is missing: %s.', $key);
            }
        }

        return array_values(array_unique($reasons));
    }
}
