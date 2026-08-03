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
use Sabri\CF02\Appeal\AppealState;
use Sabri\CF02\Appeal\ReviewerAssignmentPolicy;
use Sabri\CF02\Appeal\ReviewerProfile;
use Sabri\CF02\Domain\SupportCaseId;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('eligible appeal receives independent competent reviewer and immutable dossier', static function (): void {
    $decisionAt = new DateTimeImmutable('2026-07-20T12:00:00+05:00');
    $submittedAt = new DateTimeImmutable('2026-07-25T12:00:00+05:00');
    $eligibility = (new AppealEligibilityPolicy())->decide(
        'MOD-100',
        'user:10',
        true,
        $decisionAt,
        $submittedAt,
        30,
        ['policy_misapplied', 'new_evidence'],
        true,
        false,
        null
    );
    assert($eligibility->eligible());

    $assignment = (new ReviewerAssignmentPolicy())->assign(
        'MOD-100',
        'moderator:original',
        'content_moderation',
        false,
        [
            new ReviewerProfile('reviewer:z', ['content_moderation'], [], ['moderator:original'], true, false),
            new ReviewerProfile('reviewer:a', ['content_moderation'], [], [], true, false),
        ]
    );
    assert($assignment['reviewer_reference'] === 'reviewer:a');

    $dossier = AppealDossier::create('MOD-100', 'Policy violation recorded.', 'moderation-v7', ['evidence:original-1'], $submittedAt);
    assert($dossier->addSubmission('appellant_statement', 'submission:1', 'user:10', $submittedAt->modify('+1 hour'), 1));
    assert($dossier->verifyOriginalIntegrity('MOD-100', 'Policy violation recorded.', 'moderation-v7', ['evidence:original-1']));
});

$test('appeal lifecycle requires native decision and implementation before close', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T02:00:00+05:00');
    $dossier = AppealDossier::create('LIST-20', 'Listing removed.', 'marketplace-v4', ['evidence:list-1'], $at);
    $appeal = AppealCase::submit($caseId, 'user:20', $dossier, $at);
    $appeal->beginEligibility($at->modify('+1 minute'), 1);
    $eligibility = (new AppealEligibilityPolicy())->decide('LIST-20', 'user:20', true, $at->modify('-1 day'), $at, 30, ['procedural_error'], false, false, null);
    $appeal->recordEligibility($eligibility, $at->modify('+2 minutes'), 2);
    $appeal->assignReviewer('reviewer:marketplace', $at->modify('+3 minutes'), 3);
    $appeal->requestNativeDecision('CF02-CMD-AAAAAAAAAAAAAAAAAAAAAAAA', $at->modify('+4 minutes'), 4);
    $decision = AppealDecision::create(
        'overturn',
        'appeal-policy-v2',
        ['Original process omitted required notice.'],
        ['evidence:list-1', 'submission:user-1'],
        ['restore_listing'],
        ['Further complaint route remains available.'],
        'reviewer:marketplace',
        $at->modify('+5 minutes')
    );
    $appeal->decide($decision, 'native-outcome:restore-1', $at->modify('+5 minutes'), 5);
    assert($appeal->state() === AppealState::Decided);
    $appeal->confirmImplemented('native-outcome:restore-1', $at->modify('+6 minutes'), 6);
    $appeal->close($at->modify('+7 minutes'), 7);
    assert($appeal->state() === AppealState::Closed);
});

$test('late accessibility appeal may receive documented exception without retaliation penalty', static function (): void {
    $decision = (new AppealEligibilityPolicy())->decide(
        'ACC-1',
        'user:minor-1',
        true,
        new DateTimeImmutable('2026-01-01T00:00:00+05:00'),
        new DateTimeImmutable('2026-03-01T00:00:00+05:00'),
        30,
        ['accessibility_barrier'],
        false,
        true,
        'Screen-reader form prevented timely submission.',
        true
    );
    assert($decision->eligible());
    assert($decision->exceptionApplied());
    assert(count($decision->reasons()) >= 2);
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-E tests passed.\n");
