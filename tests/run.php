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

$test('activation gate denies missing evidence', static function (): void {
    $evidence = new class implements ActivationEvidence {
        public function runtimeSwitchEnabled(): bool { return false; }
        public function founderApproval(): array { return []; }
        public function dependencyReadiness(): array { return ['file_00' => false]; }
        public function operationalEvidence(): array { return []; }
    };

    $decision = (new ActivationGate($evidence))->evaluate();
    assert($decision->isAllowed() === false);
    assert(count($decision->reasons()) >= 3);
});

$test('activation gate permits complete evidence', static function (): void {
    $evidence = new class implements ActivationEvidence {
        public function runtimeSwitchEnabled(): bool { return true; }
        public function founderApproval(): array { return ['approved' => true, 'plan_version' => '1.0']; }
        public function dependencyReadiness(): array { return ['file_00' => true, 'file_20' => true]; }
        public function operationalEvidence(): array {
            return [
                'volume_trigger' => true,
                'staffing' => true,
                'privacy_review' => true,
                'security_review' => true,
                'rollback_plan' => true,
            ];
        }
    };

    assert((new ActivationGate($evidence))->evaluate()->isAllowed() === true);
});

$test('support case transition law', static function (): void {
    $machine = new CaseStateMachine();
    assert($machine->canTransition(CaseState::New, CaseState::Triaged));
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
