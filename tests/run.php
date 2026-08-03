<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Activation\ActivationEvidence;
use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseStateMachine;
use Sabri\CF02\Domain\InvalidTransition;

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
    'file_00_membership_contract' => true,
    'file_20_route_shell_contract' => true,
    'file_24_assurance_manifest' => true,
    'file_25_component_contract' => true,
];

$completeOperations = static fn (): array => [
    'volume_trigger' => true,
    'staffing' => true,
    'privacy_review' => true,
    'security_review' => true,
    'migration_plan' => true,
    'rollback_plan' => true,
    'zero_critical_high_defects' => true,
];

$completeApproval = static fn (): array => [
    'approved' => true,
    'plan_version' => '1.0',
    'change_control_id' => 'CF02-ACT-001',
    'approved_by' => 'founder',
    'approved_at' => '2026-08-03T17:14:00+05:00',
];

$test('activation gate denies missing evidence', static function (): void {
    $evidence = new class implements ActivationEvidence {
        public function runtimeSwitchEnabled(): bool { return false; }
        public function founderApproval(): array { return []; }
        public function dependencyReadiness(): array { return []; }
        public function operationalEvidence(): array { return []; }
    };

    $decision = (new ActivationGate($evidence))->evaluate();
    assert($decision->isAllowed() === false);
    assert(count($decision->reasons()) >= 10);
});

$test('activation gate rejects malformed approval timestamp', static function () use ($completeDependencies, $completeOperations, $completeApproval): void {
    $approval = $completeApproval();
    $approval['approved_at'] = 'not-a-date';

    $evidence = new class($approval, $completeDependencies(), $completeOperations()) implements ActivationEvidence {
        public function __construct(
            private array $approval,
            private array $dependencies,
            private array $operations
        ) {
        }
        public function runtimeSwitchEnabled(): bool { return true; }
        public function founderApproval(): array { return $this->approval; }
        public function dependencyReadiness(): array { return $this->dependencies; }
        public function operationalEvidence(): array { return $this->operations; }
    };

    $decision = (new ActivationGate($evidence))->evaluate();
    assert($decision->isAllowed() === false);
    assert(in_array('Founder approval timestamp is not valid ISO 8601.', $decision->reasons(), true));
});

$test('activation gate cannot lose a mandatory dependency through filtering', static function () use ($completeOperations, $completeApproval): void {
    $evidence = new class($completeApproval(), $completeOperations()) implements ActivationEvidence {
        public function __construct(private array $approval, private array $operations)
        {
        }
        public function runtimeSwitchEnabled(): bool { return true; }
        public function founderApproval(): array { return $this->approval; }
        public function dependencyReadiness(): array {
            return [
                'file_00_membership_contract' => true,
                'file_20_route_shell_contract' => true,
                'file_24_assurance_manifest' => true,
            ];
        }
        public function operationalEvidence(): array { return $this->operations; }
    };

    $decision = (new ActivationGate($evidence))->evaluate();
    assert($decision->isAllowed() === false);
    assert(in_array(
        'Required dependency contract is not ready: file_25_component_contract.',
        $decision->reasons(),
        true
    ));
});

$test('activation gate permits complete evidence', static function () use ($completeDependencies, $completeOperations, $completeApproval): void {
    $evidence = new class($completeApproval(), $completeDependencies(), $completeOperations()) implements ActivationEvidence {
        public function __construct(
            private array $approval,
            private array $dependencies,
            private array $operations
        ) {
        }
        public function runtimeSwitchEnabled(): bool { return true; }
        public function founderApproval(): array { return $this->approval; }
        public function dependencyReadiness(): array { return $this->dependencies; }
        public function operationalEvidence(): array { return $this->operations; }
    };

    $decision = (new ActivationGate($evidence))->evaluate();
    assert($decision->isAllowed() === true);
    assert($decision->reasons() === []);
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

fwrite(STDOUT, "All CF-02 foundation tests passed.\n");
