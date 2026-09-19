<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Appeal\AppealCase;
use Sabri\CF02\Appeal\AppealDecision;
use Sabri\CF02\Appeal\AppealDossier;
use Sabri\CF02\Appeal\AppealEligibilityPolicy;
use Sabri\CF02\Appeal\ReviewerAssignmentPolicy;
use Sabri\CF02\Domain\SupportCaseId;

$root = dirname(__DIR__);
$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('late exception without governed basis is rejected', static function (): void {
    $decision = (new AppealEligibilityPolicy())->decide(
        'DEC-LATE', 'user:4', true,
        new DateTimeImmutable('2026-01-01T00:00:00+05:00'),
        new DateTimeImmutable('2026-03-01T00:00:00+05:00'),
        30, ['policy_misapplied'], false, true, 'I forgot.'
    );
    assert(!$decision->eligible());
});

$test('malformed reviewer candidate list is rejected not ignored', static function (): void {
    $thrown = false;
    try { (new ReviewerAssignmentPolicy())->assign('DEC-X', 'actor:1', 'moderation', false, ['not-a-profile']); }
    catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('modifying outcome requires effective actions', static function (): void {
    $thrown = false;
    try {
        AppealDecision::create('modify', 'v1', ['Finding'], ['evidence:1'], [], ['Further right'], 'reviewer:1', new DateTimeImmutable());
    } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('appeal queue visibility is assigned-reviewer or File 00 queue-scope bound', static function () use ($root): void {
    $repo=(string)file_get_contents($root.'/src/Infrastructure/WordPress/OperationsRepository.php');
    $overlay=(string)file_get_contents($root.'/src/Infrastructure/WordPress/CompleteRestOverlay.php');
    assert(str_contains($repo,'Scoped appeal-queue authority is required.'));
    assert(str_contains($repo,'inQueueScope'));
    assert(str_contains($overlay,'Scoped appeal-queue authority is required.'));
    assert(str_contains($overlay,'c.queue_key IN'));
});

$test('runtime appeal flow is native-evidence-bound and not caller-approved', static function () use ($root): void {
    $controller=(string)file_get_contents($root.'/src/Infrastructure/WordPress/ComprehensiveRestController.php');
    $repo=(string)file_get_contents($root.'/src/Infrastructure/WordPress/OperationsRepository.php');
    $catalog=(string)file_get_contents($root.'/src/Contracts/SupportContractCatalog.php');
    assert(str_contains($controller,'cf02_appeal_original_decision_snapshot'));
    assert(str_contains($controller,'AppealEligibilityPolicy())->decide'));
    assert(!str_contains($controller,"\$eligible = (bool) \$request->get_param('eligible')"));
    assert(str_contains($controller,'cf02_appeal_implementation_evidence'));
    assert(str_contains($controller,'accessible reasoned decision notice is delivered'));
    assert(str_contains($controller,'Only the independently assigned reviewer may perform this appeal-review action.'));
    assert(str_contains($repo,"'standing_verified' => true"));
    assert(str_contains($repo,'appealDecisionNoticeSent'));
    assert(str_contains($catalog,"'ReopenAppeal'"));
    assert(str_contains($catalog,"'AppealReopened'"));
});

$test('domain appeal separates decision outcome from implementation proof and closes only after implementation', static function (): void {
    $at=new DateTimeImmutable('2026-08-04T04:00:00+05:00');
    $appeal=AppealCase::submit(SupportCaseId::generate(),'user:9',AppealDossier::create('DEC-9','Reason.','v1',['evidence:9'],$at),$at);
    $appeal->beginEligibility($at->modify('+1 minute'),1);
    $appeal->recordEligibility((new AppealEligibilityPolicy())->decide('DEC-9','user:9',true,$at->modify('-1 day'),$at,30,['policy_misapplied'],false,false,null),$at->modify('+2 minutes'),2);
    $appeal->assignReviewer('reviewer:9',$at->modify('+3 minutes'),3);
    $appeal->requestNativeDecision('CF02-CMD-CCCCCCCCCCCCCCCCCCCC',$at->modify('+4 minutes'),4);
    $decision=AppealDecision::create('uphold','v1',['Finding'],['evidence:9'],[],['Further right'],'reviewer:9',$at->modify('+5 minutes'));
    $appeal->decide($decision,'native:9',$at->modify('+5 minutes'),5);
    assert($appeal->nativeOutcomeReference()==='native:9');
    assert($appeal->implementationReference()===null);
    $blocked=false;try{$appeal->close($at->modify('+6 minutes'),6);}catch(DomainException){$blocked=true;}assert($blocked);
    $appeal->confirmImplemented('native:9',$at->modify('+7 minutes'),6);
    assert($appeal->implementationReference()==='native:9');
    $appeal->close($at->modify('+8 minutes'),7);
});

$test('implementation reference mismatch keeps appeal open', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T04:00:00+05:00');
    $appeal = AppealCase::submit(SupportCaseId::generate(), 'user:5', AppealDossier::create('DEC-5', 'Reason.', 'v1', ['evidence:5'], $at), $at);
    $appeal->beginEligibility($at->modify('+1 minute'), 1);
    $appeal->recordEligibility((new AppealEligibilityPolicy())->decide('DEC-5', 'user:5', true, $at->modify('-1 day'), $at, 30, ['policy_misapplied'], false, false, null), $at->modify('+2 minutes'), 2);
    $appeal->assignReviewer('reviewer:5', $at->modify('+3 minutes'), 3);
    $appeal->requestNativeDecision('CF02-CMD-BBBBBBBBBBBBBBBBBBBBBBBB', $at->modify('+4 minutes'), 4);
    $decision = AppealDecision::create('overturn', 'v2', ['Error found'], ['evidence:5'], ['restore'], ['Further right'], 'reviewer:5', $at->modify('+5 minutes'));
    $appeal->decide($decision, 'native:expected', $at->modify('+5 minutes'), 5);
    $thrown = false;
    try { $appeal->confirmImplemented('native:other', $at->modify('+6 minutes'), 6); }
    catch (DomainException) { $thrown = true; }
    assert($thrown);
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-E second-review adversarial tests passed.\n");
