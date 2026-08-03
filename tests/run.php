<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Activation\ActivationEvidence;
use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Configuration\QueueDefinition;
use Sabri\CF02\Configuration\QueueRegistry;
use Sabri\CF02\Configuration\SupportCategory;
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

$contracts = static fn (): array => [
    'file_00_membership_contract' => ['ready' => true, 'owner' => 'File 00', 'contract_version' => '1.1.2', 'capabilities' => ['identity_assertions']],
    'file_09_verification_contract' => ['ready' => true, 'owner' => 'File 09', 'contract_version' => '1.0.0', 'capabilities' => ['verification_decision_reference']],
    'file_17_message_report_contract' => ['ready' => true, 'owner' => 'File 17', 'contract_version' => '2.0.0', 'capabilities' => ['message_report_decision_reference']],
    'file_18_marketplace_case_contract' => ['ready' => true, 'owner' => 'File 18', 'contract_version' => '1.0.0', 'capabilities' => ['listing_decision_reference']],
    'file_20_route_shell_contract' => ['ready' => true, 'owner' => 'File 20', 'contract_version' => '1.0.0', 'capabilities' => ['route_shell_mount']],
    'file_21_content_case_contract' => ['ready' => true, 'owner' => 'File 21', 'contract_version' => '1.0.0', 'capabilities' => ['content_decision_reference']],
    'file_24_assurance_manifest' => ['ready' => true, 'owner' => 'File 24', 'contract_version' => '1.0.0', 'capabilities' => ['assurance_manifest']],
    'file_25_component_contract' => ['ready' => true, 'owner' => 'File 25', 'contract_version' => '1.0.0', 'capabilities' => ['component_manifest']],
];

$evidenceRecord = static fn (string $id, string $owner): array => [
    'status' => 'accepted',
    'evidence_id' => $id,
    'owner' => $owner,
    'artifact_ref' => 'evidence/' . strtolower($id),
    'recorded_at' => '2026-08-03T17:41:00+05:00',
];

$operations = static function () use ($evidenceRecord): array {
    return [
        'volume_trigger' => array_merge($evidenceRecord('VOL-001', 'Support Operations'), [
            'measurement_window' => '90 days',
            'metric' => 'safe_capacity_exceedance_days',
            'threshold' => 20,
            'observed_value' => 27,
            'triggered' => true,
        ]),
        'staffing' => array_merge($evidenceRecord('STAFF-001', 'Support Operations'), [
            'coverage_hours' => 'coverage.v1',
            'queue_owners' => ['account' => 'team-a', 'technical' => 'team-b'],
            'escalation_tree_approved' => true,
            'emergency_diversion_approved' => true,
            'privacy_training_complete' => true,
            'quality_sampling_approved' => true,
        ]),
        'privacy_review' => $evidenceRecord('PRIV-001', 'Privacy Reviewer'),
        'security_review' => $evidenceRecord('SEC-001', 'Security Reviewer'),
        'migration_plan' => $evidenceRecord('MIG-001', 'Migration Owner'),
        'rollback_plan' => $evidenceRecord('RB-001', 'Release Owner'),
        'zero_critical_high_defects' => array_merge($evidenceRecord('DEF-001', 'QA Owner'), ['critical_open' => 0, 'high_open' => 0]),
    ];
};

$approval = static fn (): array => [
    'approved' => true,
    'plan_version' => '1.0',
    'change_control_id' => 'CF02-ACT-001',
    'approved_by' => 'founder',
    'approved_at' => '2026-08-03T17:14:00+05:00',
];

$provider = static function (array $approvalData, array $contractData, array $operationData, bool $enabled = true): ActivationEvidence {
    return new class($approvalData, $contractData, $operationData, $enabled) implements ActivationEvidence {
        public function __construct(
            private array $approval,
            private array $contracts,
            private array $operations,
            private bool $enabled
        ) {
        }
        public function runtimeSwitchEnabled(): bool { return $this->enabled; }
        public function founderApproval(): array { return $this->approval; }
        public function dependencyReadiness(): array { return $this->contracts; }
        public function operationalEvidence(): array { return $this->operations; }
    };
};

$decision = static fn (array $a, array $c, array $o, bool $enabled = true) => (new ActivationGate($provider($a, $c, $o, $enabled)))->evaluate();

$test('activation denies empty evidence', static function () use ($decision): void {
    $result = $decision([], [], [], false);
    assert(!$result->isAllowed());
    assert(count($result->reasons()) >= 15);
});

$test('activation requires canonical Founder identity and valid approval ID', static function () use ($approval, $contracts, $operations, $decision): void {
    $a = $approval();
    $a['approved_by'] = 'administrator';
    $a['change_control_id'] = 'latest';
    $result = $decision($a, $contracts(), $operations());
    assert(!$result->isAllowed());
    assert(in_array('Activation approval is not bound to the canonical Founder identity.', $result->reasons(), true));
    assert(in_array('Founder activation change-control ID is invalid.', $result->reasons(), true));
});

$test('activation rejects malformed approval time', static function () use ($approval, $contracts, $operations, $decision): void {
    $a = $approval();
    $a['approved_at'] = 'not-a-date';
    assert(in_array('Founder approval timestamp is not valid ISO 8601.', $decision($a, $contracts(), $operations())->reasons(), true));
});

$test('activation requires every owner contract', static function () use ($approval, $contracts, $operations, $decision): void {
    $c = $contracts();
    unset($c['file_21_content_case_contract']);
    assert(in_array('Required dependency contract is not ready: file_21_content_case_contract.', $decision($approval(), $c, $operations())->reasons(), true));
});

$test('activation rejects owner spoofing version drift and missing capability', static function () use ($approval, $contracts, $operations, $decision): void {
    $c = $contracts();
    $c['file_24_assurance_manifest']['owner'] = 'Other';
    $c['file_20_route_shell_contract']['contract_version'] = 'latest';
    $c['file_17_message_report_contract']['capabilities'] = [];
    $reasons = $decision($approval(), $c, $operations())->reasons();
    assert(in_array('Dependency contract owner mismatch: file_24_assurance_manifest.', $reasons, true));
    assert(in_array('Dependency contract version is missing or invalid: file_20_route_shell_contract.', $reasons, true));
    assert(in_array('Dependency contract capability is missing: file_17_message_report_contract.', $reasons, true));
});

$test('activation rejects boolean-only operational claims', static function () use ($approval, $contracts, $decision): void {
    $booleans = array_fill_keys(['volume_trigger', 'staffing', 'privacy_review', 'security_review', 'migration_plan', 'rollback_plan', 'zero_critical_high_defects'], true);
    assert(in_array('Required operational evidence is missing or unaccepted: staffing.', $decision($approval(), $contracts(), $booleans)->reasons(), true));
});

$test('activation cross-checks measured trigger', static function () use ($approval, $contracts, $operations, $decision): void {
    $o = $operations();
    $o['volume_trigger']['observed_value'] = 5;
    $reasons = $decision($approval(), $contracts(), $o)->reasons();
    assert(in_array('Observed volume does not meet the declared extraction threshold.', $reasons, true));
});

$test('activation rejects incomplete staffing evidence', static function () use ($approval, $contracts, $operations, $decision): void {
    $o = $operations();
    $o['staffing']['queue_owners'] = ['account' => ''];
    $o['staffing']['privacy_training_complete'] = false;
    $reasons = $decision($approval(), $contracts(), $o)->reasons();
    assert(in_array('Staffing queue-owner evidence contains an invalid assignment.', $reasons, true));
    assert(in_array('Staffing evidence is incomplete: privacy_training_complete.', $reasons, true));
});

$test('activation permits complete structured evidence', static function () use ($approval, $contracts, $operations, $decision): void {
    $result = $decision($approval(), $contracts(), $operations());
    assert($result->isAllowed());
    assert($result->reasons() === []);
});

$test('taxonomy and purpose-separated queues validate', static function (): void {
    $taxonomy = SupportTaxonomy::defaults();
    assert(count($taxonomy) === 12);
    assert(SupportTaxonomy::validate() === []);
    assert($taxonomy['account_access']->queueKey() === 'account');
    assert($taxonomy['verification']->queueKey() === 'verification');
    assert($taxonomy['privacy_data_rights']->queueKey() === 'privacy_liaison');
    assert($taxonomy['safety_abuse']->queueKey() === 'safety_liaison');
    assert(QueueRegistry::validate() === []);
    assert(count(QueueRegistry::defaults()) === 11);
});

$test('configuration constructors reject malformed lists', static function (): void {
    $categoryThrown = false;
    try {
        new SupportCategory('bad', 'Bad', 'technical', [['not-a-string']], 'C2', 'Owner', ['issue_type']);
    } catch (InvalidArgumentException) {
        $categoryThrown = true;
    }
    assert($categoryThrown);

    $queueThrown = false;
    try {
        new QueueDefinition('bad', 'Bad', ['technical', 'technical'], ['technical_support'], 'team_lead', 'coverage.bad.v1', 'specialist_agent');
    } catch (InvalidArgumentException) {
        $queueThrown = true;
    }
    assert($queueThrown);
});

$test('high-risk staffing separation rejects conflicts and accepts distinct actors', static function (): void {
    $bad = StaffingRegistry::validateHighRiskSeparation([
        'requester' => 'user-1',
        'original_decider' => 'agent-1',
        'reviewer' => 'agent-1',
        'executor' => 'agent-1',
        'reconciler' => 'agent-2',
        'auditor' => 'agent-1',
    ]);
    assert(count($bad) >= 3);

    $good = StaffingRegistry::validateHighRiskSeparation([
        'requester' => 'user-1',
        'original_decider' => 'agent-1',
        'reviewer' => 'reviewer-1',
        'executor' => 'executor-1',
        'reconciler' => 'reconciler-1',
        'auditor' => 'auditor-1',
    ]);
    assert($good === []);
});

$test('change-control validates complete records and rejects missing rollback', static function (): void {
    $record = [
        'id' => 'CF02-CCR-0001',
        'requested_by' => 'Founder',
        'recorded_at' => '2026-08-03T17:41:00+05:00',
        'affected_files' => ['CF-02', 'File 24'],
        'requirement_ids' => ['CF02-FR-030'],
        'old_rule' => 'No baseline',
        'new_rule' => 'Dormant foundation',
        'rationale' => 'Controlled implementation',
        'data_impact' => 'No runtime data',
        'security_privacy_impact' => 'Fail closed',
        'shariah_impact' => 'No change',
        'migration_plan' => 'None',
        'rollback_plan' => 'Revert branch',
        'test_plan' => 'Automated tests',
        'approval_status' => 'implementation_authorized',
    ];
    assert(ChangeControlRecord::validate($record) === []);
    unset($record['rollback_plan']);
    assert(in_array('Change-control field is missing: rollback_plan.', ChangeControlRecord::validate($record), true));
});

$test('support case state law rejects illegal closure', static function (): void {
    $machine = new CaseStateMachine();
    assert($machine->canTransition(CaseState::New, CaseState::Triaged));
    assert($machine->canTransition(CaseState::InProgress, CaseState::WaitingForUser));
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
