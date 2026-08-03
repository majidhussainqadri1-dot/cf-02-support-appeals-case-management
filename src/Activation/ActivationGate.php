<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use DateTimeImmutable;

final class ActivationGate
{
    /** @var array<string, string> */
    private const REQUIRED_DEPENDENCIES = [
        'file_00_membership_contract' => 'File 00',
        'file_20_route_shell_contract' => 'File 20',
        'file_24_assurance_manifest' => 'File 24',
        'file_25_component_contract' => 'File 25',
    ];

    /** @var list<string> */
    private const REQUIRED_OPERATIONAL_EVIDENCE = [
        'volume_trigger',
        'staffing',
        'privacy_review',
        'security_review',
        'migration_plan',
        'rollback_plan',
        'zero_critical_high_defects',
    ];

    public function __construct(private readonly ActivationEvidence $evidence)
    {
    }

    public function evaluate(): ActivationDecision
    {
        $reasons = [];
        $founderApproval = $this->evidence->founderApproval();
        $dependencies = $this->evidence->dependencyReadiness();
        $operations = $this->evidence->operationalEvidence();

        if (!$this->evidence->runtimeSwitchEnabled()) {
            $reasons[] = 'The explicit runtime switch is disabled.';
        }

        if (($founderApproval['approved'] ?? false) !== true) {
            $reasons[] = 'Founder activation approval is not recorded.';
        }

        if (($founderApproval['plan_version'] ?? null) !== '1.0') {
            $reasons[] = 'Founder approval does not target governing plan version 1.0.';
        }

        foreach (['change_control_id', 'approved_by', 'approved_at'] as $field) {
            if (!isset($founderApproval[$field]) || !is_string($founderApproval[$field]) || trim($founderApproval[$field]) === '') {
                $reasons[] = sprintf('Founder approval field is missing: %s.', $field);
            }
        }

        if (
            isset($founderApproval['approved_at'])
            && is_string($founderApproval['approved_at'])
            && !$this->isIso8601Timestamp($founderApproval['approved_at'])
        ) {
            $reasons[] = 'Founder approval timestamp is not valid ISO 8601.';
        }

        foreach (self::REQUIRED_DEPENDENCIES as $dependency => $expectedOwner) {
            $contract = $dependencies[$dependency] ?? null;

            if (!is_array($contract) || ($contract['ready'] ?? false) !== true) {
                $reasons[] = sprintf('Required dependency contract is not ready: %s.', $dependency);
                continue;
            }

            if (($contract['owner'] ?? null) !== $expectedOwner) {
                $reasons[] = sprintf('Dependency contract owner mismatch: %s.', $dependency);
            }

            $contractVersion = $contract['contract_version'] ?? null;
            if (!is_string($contractVersion) || !$this->isVersionIdentifier($contractVersion)) {
                $reasons[] = sprintf('Dependency contract version is missing or invalid: %s.', $dependency);
            }
        }

        foreach (self::REQUIRED_OPERATIONAL_EVIDENCE as $requiredEvidence) {
            if (($operations[$requiredEvidence] ?? false) !== true) {
                $reasons[] = sprintf('Required operational evidence is missing: %s.', $requiredEvidence);
            }
        }

        $allEvidence = [
            'founder_approval' => $founderApproval,
            'dependencies' => $dependencies,
            'operations' => $operations,
        ];

        if ($reasons !== []) {
            return ActivationDecision::deny(array_values(array_unique($reasons)), $allEvidence);
        }

        return ActivationDecision::allow($allEvidence);
    }

    private function isIso8601Timestamp(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function isVersionIdentifier(string $version): bool
    {
        return preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }
}
