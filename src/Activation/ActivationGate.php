<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\CF02\Contracts\RequiredCompanionContracts;

final class ActivationGate
{
    public function __construct(private readonly ActivationEvidence $evidence, private readonly ?DateTimeImmutable $clock = null) {}

    public function evaluate(): ActivationDecision
    {
        $reasons = [];
        $now = $this->clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $environment = $this->evidence->runtimeEnvironment();
        $identity = $this->evidence->exactRuntimeIdentity();
        $approval = $this->evidence->founderApproval();
        $dependencies = $this->evidence->dependencyReadiness();
        $operations = $this->evidence->operationalEvidence();

        if (!$this->evidence->runtimeSwitchEnabled()) { $reasons[] = 'The explicit runtime switch is disabled.'; }
        if (!in_array($environment, ['staging','production'], true)) { $reasons[] = 'Runtime environment is invalid.'; }
        if (($approval['approved'] ?? false) !== true) { $reasons[] = 'Founder activation approval is not recorded.'; }
        if (($approval['plan_version'] ?? null) !== '1.0') { $reasons[] = 'Founder approval does not target governing plan version 1.0.'; }
        foreach (['change_control_id','approved_by','approved_at','environment','runtime_version','schema_version','source_sha','package_sha256'] as $field) {
            if (!isset($approval[$field]) || !is_string($approval[$field]) || trim($approval[$field]) === '') {
                $reasons[] = sprintf('Founder approval field is missing: %s.', $field);
            }
        }
        if (($approval['approved_by'] ?? null) !== 'founder') { $reasons[] = 'Activation approval is not bound to the canonical Founder identity.'; }
        if (($approval['environment'] ?? null) !== $environment) { $reasons[] = 'Founder approval does not target the active runtime environment.'; }

        $formats = ['runtime_version'=>'/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/','schema_version'=>'/^\d+\.\d+\.\d+$/','source_sha'=>'/^[a-f0-9]{40}$/','package_sha256'=>'/^[a-f0-9]{64}$/'];
        foreach ($formats as $field => $pattern) {
            $actual = $identity[$field] ?? '';
            if (!is_string($actual) || preg_match($pattern, $actual) !== 1) {
                $reasons[] = sprintf('Exact runtime identity is invalid: %s.', $field);
                continue;
            }
            if (($approval[$field] ?? null) !== $actual) {
                $reasons[] = sprintf('Founder approval does not target the exact artifact: %s.', $field);
            }
        }
        if (isset($approval['change_control_id']) && is_string($approval['change_control_id']) && preg_match('/^CF02-ACT-\d{3,4}$/', $approval['change_control_id']) !== 1) {
            $reasons[] = 'Founder activation change-control ID is invalid.';
        }
        if (isset($approval['approved_at']) && is_string($approval['approved_at'])) {
            $approvedAt = $this->date($approval['approved_at']);
            if (!$approvedAt) { $reasons[] = 'Founder approval timestamp is not valid ISO 8601.'; }
            elseif ($approvedAt > $now->modify('+5 minutes')) { $reasons[] = 'Founder approval timestamp is in the future.'; }
        }

        $reasons = array_merge(
            $reasons,
            RequiredCompanionContracts::validate($dependencies, $now),
            OperationalEvidenceRegistry::validate($operations, $environment, $identity, $now)
        );
        $all = ['environment'=>$environment,'runtime_identity'=>$identity,'founder_approval'=>$approval,'dependencies'=>$dependencies,'operations'=>$operations];
        return $reasons === [] ? ActivationDecision::allow($all) : ActivationDecision::deny(array_values(array_unique($reasons)), $all);
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) { return null; }
        try { return new DateTimeImmutable($value); } catch (\Throwable) { return null; }
    }
}
