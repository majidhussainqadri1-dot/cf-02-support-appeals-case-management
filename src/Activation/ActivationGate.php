<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use DateTimeImmutable;
use Sabri\CF02\Contracts\RequiredCompanionContracts;

final class ActivationGate
{
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

        if (($founderApproval['approved_by'] ?? null) !== 'founder') {
            $reasons[] = 'Activation approval is not bound to the canonical Founder identity.';
        }

        if (
            isset($founderApproval['change_control_id'])
            && is_string($founderApproval['change_control_id'])
            && preg_match('/^CF02-ACT-\d{3,4}$/', $founderApproval['change_control_id']) !== 1
        ) {
            $reasons[] = 'Founder activation change-control ID is invalid.';
        }

        if (
            isset($founderApproval['approved_at'])
            && is_string($founderApproval['approved_at'])
            && !$this->isIso8601Timestamp($founderApproval['approved_at'])
        ) {
            $reasons[] = 'Founder approval timestamp is not valid ISO 8601.';
        }

        $reasons = array_merge(
            $reasons,
            RequiredCompanionContracts::validate($dependencies),
            OperationalEvidenceRegistry::validate($operations)
        );

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
}
