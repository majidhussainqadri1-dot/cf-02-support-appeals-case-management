<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

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

        foreach ($dependencies as $dependency => $ready) {
            if ($ready !== true) {
                $reasons[] = sprintf('Required dependency contract is not ready: %s.', $dependency);
            }
        }

        foreach (['volume_trigger', 'staffing', 'privacy_review', 'security_review', 'rollback_plan'] as $requiredEvidence) {
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
            return ActivationDecision::deny($reasons, $allEvidence);
        }

        return ActivationDecision::allow($allEvidence);
    }
}
