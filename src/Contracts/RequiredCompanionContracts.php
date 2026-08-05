<?php

declare(strict_types=1);

namespace Sabri\CF02\Contracts;

use DateTimeImmutable;

final class RequiredCompanionContracts
{
    /** @var array<string,array{owner:string,capability:string,required:bool,condition?:string}> */
    private const DEFINITIONS = [
        'file_00_membership_contract' => ['owner'=>'File 00','capability'=>'identity_assertions','required'=>true],
        'file_02_authentication_contract' => ['owner'=>'File 02','capability'=>'account_recovery_commands','required'=>true],
        'file_09_verification_contract' => ['owner'=>'File 09','capability'=>'verification_decision_reference','required'=>true],
        'file_17_message_report_contract' => ['owner'=>'File 17','capability'=>'message_report_decision_reference','required'=>true],
        'file_18_marketplace_case_contract' => ['owner'=>'File 18','capability'=>'listing_decision_reference','required'=>true],
        'file_19_notification_contract' => ['owner'=>'File 19','capability'=>'notification_delivery_requests','required'=>true],
        'file_20_route_shell_contract' => ['owner'=>'File 20','capability'=>'route_shell_mount','required'=>true],
        'file_21_content_case_contract' => ['owner'=>'File 21','capability'=>'content_decision_reference','required'=>true],
        'file_24_assurance_manifest' => ['owner'=>'File 24','capability'=>'assurance_manifest','required'=>true],
        'file_25_component_contract' => ['owner'=>'File 25','capability'=>'component_manifest','required'=>true],
        'file_26_ranking_contract' => ['owner'=>'File 26','capability'=>'privacy_safe_case_outcome_projection','required'=>false,'condition'=>'ranking_outcome_projection_enabled'],
        'attachment_storage_provider' => ['owner'=>'Approved Attachment Storage','capability'=>'private_quarantine_storage','required'=>true],
        'malware_scanner_provider' => ['owner'=>'Approved Malware Scanner','capability'=>'malware_scan_verdict','required'=>true],
        'email_adapter_provider' => ['owner'=>'Approved Email Adapter','capability'=>'signed_inbound_email','required'=>true],
        'managed_key_provider' => ['owner'=>'Approved Key Provider','capability'=>'versioned_keyring_rotation','required'=>true],
        'cf_03_financial_contract' => ['owner'=>'CF-03/provider','capability'=>'financial_action_request','required'=>false,'condition'=>'financial_flows_enabled'],
        'clinical_safety_owner_contract' => ['owner'=>'File 08/CF-01','capability'=>'clinical_safety_diversion_reference','required'=>false,'condition'=>'clinical_diversion_enabled'],
    ];

    /** @return array<string,array{owner:string,capability:string,required:bool,condition?:string}> */
    public static function definitions(): array { return self::DEFINITIONS; }

    /** @param array<string,mixed> $contracts @return list<string> */
    public static function validate(array $contracts, DateTimeImmutable $now): array
    {
        $reasons = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $contract = $contracts[$key] ?? null;
            $enabled = is_array($contract) && ($contract['enabled'] ?? false) === true;
            $required = $definition['required'] || $enabled;
            if (!$required) { continue; }
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
            if (($contract['health'] ?? null) !== 'healthy') {
                $reasons[] = sprintf('Dependency contract health is not healthy: %s.', $key);
            }
            $checkedAt = self::date((string)($contract['health_checked_at'] ?? ''));
            if (!$checkedAt) {
                $reasons[] = sprintf('Dependency contract health evidence is invalid: %s.', $key);
            } elseif ($checkedAt > $now->modify('+60 seconds')) {
                $reasons[] = sprintf('Dependency contract health evidence is in the future: %s.', $key);
            } elseif ($checkedAt < $now->modify('-15 minutes')) {
                $reasons[] = sprintf('Dependency contract health evidence is stale: %s.', $key);
            }
        }
        return array_values(array_unique($reasons));
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) { return null; }
        try { return new DateTimeImmutable($value); } catch (\Throwable) { return null; }
    }
}
