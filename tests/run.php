<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Activation\ActivationEvidence;
use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Configuration\QueueRegistry;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseStateMachine;
use Sabri\CF02\Domain\InvalidTransition;
use Sabri\CF02\Governance\ChangeControlRecord;
use Sabri\CF02\Staffing\StaffingRegistry;

$failures = [];

$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures[] = $name . ': ' . $exception->getMessage();
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
};

$completeDependencies = static fn (): array => [
    'file_00_membership_contract' => [
        'ready' => true,
        'owner' => 'File 00',
        'contract_version' => '1.1.2',
        'capabilities' => ['identity_assertions'],
    ],
    'file_09_verification_contract' => [
        'ready' => true,
        'owner' => 'File 09',
        'contract_version' => '1.0.0',
        'capabilities' => ['verification_decision_reference'],
    ],
    'file_17_message_report_contract' => [
        'ready' => true,
        'owner' => 'File 17',
        'contract_version' => '2.0.0',
        'capabilities' => ['message_report_decision_reference'],
    ],
    'file_18_marketplace_case_contract' => [
        'ready' => true,
        'owner' => 'File 18',
        'contract_version' => '1.0.0',
        'capabilities' => ['listing_decision_reference'],
    ],
    'file_20_route_shell_contract' => [
        'ready' => true,
        'owner' => 'File 20',
        'contract_version' => '1.0.0',
        'capabilities' => ['route_shell_mount'],
    ],
    'file_21_content_case_contract' => [
        'ready' => true,
        'owner' => 'File 21',
        'contract_version' => '1.0.0',
        'capabilities' => ['content_decision_reference'],
    ],
    'file_24_assurance_manifest' => [
        'ready' => true,
        'owner' => 'File 24',
        'contract_version' => '1.0.0',
        'capabilities' => ['assurance_manifest'],
    ],
    'file_25_component_contract' => [
        'ready' => true,
        'owner' => 'File 25',
        'contract_version' => '1.0.0',
        'capabilities' => ['component_manifest'],
    ],
];

$completeOperations = static function (): array {
    $base = static fn (string $id, string $owner): array => [
        'status' => 'accepted',
        'evidence_id' => $id,
        'owner' => $owner,
        'artifact_ref' => 'evidence/' . strtolower($id),
        'recorded_at' => '2026-08-03T17:41:00+05:00',
    ];

    return [
        'volume_trigger' => array_merge($base('VOL-001', 'Support Operations'), [
            'measurement_window' => '90 days',
            'metric' => 'safe_capacity_exceedance_days',
            'threshold' => 20,
            'observed_value' => 27,
            'triggered' => true,
        ]),
        'staffing' => array_merge($base('STAFF-001', 'Support Operations'), [
            'coverage_hours' => 'Approved schedule reference coverage.v1',
            'queue_owners' => ['identity' => 'team-a', 'technical' => 'team-b'],
            'escalation_tree_approved' => true,
            'emergency_diversion_approved' => true,
            'privacy_training_complete' => true,
            'quality_sampling_approved' => true,
        ]),
        'privacy_review' => $base('PRIV-001', 'Privacy Reviewer'),
        'security_review' => $base('SEC-001', 'Security Reviewer'),
        'migration_plan' => $base('MIG-001', 'Migration Owner'),
        'rollback_plan' => $base('RB-001', 'Release Owner'),
        'zero_critical_high_defects' => array_merge($base('DEF-001', 'QA Owner'), [
            'critical_open' => 0,
            'high_open' => 0,
        ]),
    ];
};

$completeApproval = static fn (): array => [
    'approved' => true,
    'plan_version' => '1.0',
    'change_control_id' => 'CF02-ACT-001',
    'approved_by' => 'founder',
    'approved_at' => '2026-08-03T17:14:00+05:00',
];

$makeEvidence = static function (array $approval, array $dependencies, array $operations, bool $switch = true): ActivationEvidence {
    return new class($approval, $dependencies, $operations, $switch) implements ActivationEvidence {
        public function __construct(
            private array $approval,
            private array $dependencies,
            private array $operations,
            private bool $switch
        ) {
        }
        public function runtimeSwitchEnabled(): bool { return $this->switch; }
        public function founderApproval(): array { return $this->approval; }
        public function dependencyReadiness(): array { return $this->dependencies; }
        public function operationalEvidence(): array { return $this->operations; }
    };
};

$test('activation gate denies missing evidence', static function () use ($makeEvidence): void {
    $decision = (new ActivationGate($makeEvidence([], [], [], false)))->evaluate();
    assert($decision->isAllowed() === false);
    assert(count($decision->reasons()) >= 15);
});

$test('activation gate rejects malformed approval timestamp', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $approval = $completeApproval();
    $approval['approved_at'] = 'not-a-date';
    $decision = (new ActivationGate($makeEvidence($approval, $completeDependencies(), $completeOperations())))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Founder approval timestamp is not valid ISO 8601.', $decision->reasons(), true));
});

$test('activation gate cannot lose a mandatory owner contract', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $dependencies = $completeDependencies();
    unset($dependencies['file_21_content_case_contract']);
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $dependencies, $completeOperations())))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Required dependency contract is not ready: file_21_content_case_contract.', $decision->reasons(), true));
});

$test('activation gate rejects owner spoofing', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $dependencies = $completeDependencies();
    $dependencies['file_24_assurance_manifest']['owner'] = 'Unknown Plugin';
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $dependencies, $completeOperations())))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Dependency contract owner mismatch: file_24_assurance_manifest.', $decision->reasons(), true));
});

$test('activation gate rejects invalid contract version', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $dependencies = $completeDependencies();
    $dependencies['file_20_route_shell_contract']['contract_version'] = 'latest';
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $dependencies, $completeOperations())))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Dependency contract version is missing or invalid: file_20_route_shell_contract.', $decision->reasons(), true));
});

$test('activation gate rejects missing declared capability', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $dependencies = $completeDependencies();
    $dependencies['file_17_message_report_contract']['capabilities'] = [];
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $dependencies, $completeOperations())))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Dependency contract capability is missing: file_17_message_report_contract.', $decision->reasons(), true));
});

$test('activation gate rejects boolean-only operational claims', static function () use ($completeDependencies, $completeApproval, $makeEvidence): void {
    $operations = [
        'volume_trigger' => true,
        'staffing' => true,
        'privacy_review' => true,
        'security_review' => true,
        'migration_plan' => true,
        'rollback_plan' => true,
        'zero_critical_high_defects' => true,
    ];
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $completeDependencies(), $operations)))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Required operational evidence is missing or unaccepted: staffing.', $decision->reasons(), true));
});

$test('activation gate rejects an unsatisfied measured volume trigger', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $operations = $completeOperations();
    $operations['volume_trigger']['triggered'] = false;
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $completeDependencies(), $operations)))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Measured extraction trigger has not been satisfied.', $decision->reasons(), true));
});

$test('activation gate rejects incomplete staffing evidence', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $operations = $completeOperations();
    $operations['staffing']['privacy_training_complete'] = false;
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $completeDependencies(), $operations)))->evaluate();
    assert(!$decision->isAllowed());
    assert(in_array('Staffing evidence is incomplete: privacy_training_complete.', $decision->reasons(), true));
});

$test('activation gate permits complete structured evidence', static function () use ($completeDependencies, $completeOperations, $completeApproval, $makeEvidence): void {
    $decision = (new ActivationGate($makeEvidence($completeApproval(), $completeDependencies(), $completeOperations())))->evaluate();
    assert($decision->isAllowed());
    assert($decision->reasons() === []);
});

$test('support taxonomy covers every approved category', static function (): void {
    $taxonomy = SupportTaxonomy::defaults();
    assert(count($taxonomy) === 12);
    assert(SupportTaxonomy::validate() === []);
    assert($taxonomy['privacy_data_rights']->specialistOnly());
    assert($taxonomy['technical']->nativeOwner() === 'Platform operations');
});

$test('queue registry has complete unique category and skill coverage', static function (): void {
    assert(QueueRegistry::validate() === []);
    assert(count(QueueRegistry::defaults()) === 9);
});

$test('high-risk staffing separation rejects conflicts', static function (): void {
    $reasons = StaffingRegistry::validateHighRiskSeparation([
        'requester' => 'user-1',
        'original_decider' => 'agent-1',
        'reviewer' => 'agent-1',
        'executor' => 'agent-1',
        'reconciler' => 'agent-2',
        'auditor' => 'agent-1',
    ]);
    assert(count($reasons) >= 3);
    assert(in_array('Appeal reviewer cannot be the original decision maker.', $reasons, true));
});

$test('high-risk staffing separation accepts distinct actors', static function (): void {
    $reasons = StaffingRegistry::validateHighRiskSeparation([
        'requester' => 'user-1',
        'original_decider' => 'agent-1',
        'reviewer' => 'reviewer-1',
        'executor' => 'executor-1',
        'reconciler' => 'reconciler-1',
        'auditor' => 'auditor-1',
    ]);
    assert($reasons === []);
});

$test('change-control validator accepts complete implementation record', static function (): void {
    $record = [
        'id' => 'CF02-CCR-0001',
        'requested_by' => 'Founder',
        'recorded_at' => '2026-08-03T17:41:00+05:00',
        'affected_files' => ['CF-02', 'File 00', 'File 24'],
        'requirement_ids' => ['CF02-FR-030'],
        'old_rule' => 'No repository baseline.',
        'new_rule' => 'Dormant governed foundation.',
        'rationale' => 'Begin controlled implementation.',
        'data_impact' => 'No runtime data.',
        'security_privacy_impact' => 'Fail closed.',
        'shariah_impact' => 'No change.',
        'migration_plan' => 'None in foundation.',
        'rollback_plan' => 'Revert branch.',
        'test_plan' => 'Syntax and pure tests.',
        'approval_status' => 'implementation_authorized',
    ];
    assert(ChangeControlRecord::validate($record) === []);
});

$test('change-control validator rejects missing rollback', static function (): void {
    $reasons = ChangeControlRecord::validate([
        'id' => 'CF02-CCR-0002',
        'requested_by' => 'Founder',
        'recorded_at' => '2026-08-03T17:41:00+05:00',
        'affected_files' => ['CF-02'],
        'requirement_ids' => ['CF02-FR-030'],
        'old_rule' => 'A',
        'new_rule' => 'B',
        'rationale' => 'Reason',
        'data_impact' => 'None',
        'security_privacy_impact' => 'Reviewed',
        'shariah_impact' => 'Reviewed',
        'migration_plan' => 'None',
        'test_plan' => 'Tests',
        'approval_status' => 'pending',
    ]);
    assert(in_array('Change-control field is missing: rollback_plan.', $reasons, true));
});

$test('support case transition law', static function (): void {
    $machine = new CaseStateMachine();
    assert($machine->canTransition(CaseState::New, CaseState::Triaged));
    assert($machine->canTransition(CaseState::InProgress, CaseState::WaitingForUser));
    assert($machine->canTransition(CaseState::Resolved, CaseState::Closed));
    assert($machine->canTransition(CaseState::Closed, CaseState::Reopened));
    assert(!$machine->canTransition(CaseState::New, CaseState::Closed));

    $thrown = false;
    try {
        $machine->assertTransition(CaseState::New, CaseState::Closed);
    } catch (InvalidTransition) {
        $thrown = true;
    }
    assert($thrown);
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-A foundation tests passed.\n");
