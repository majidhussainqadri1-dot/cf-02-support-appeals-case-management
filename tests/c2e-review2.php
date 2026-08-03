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
