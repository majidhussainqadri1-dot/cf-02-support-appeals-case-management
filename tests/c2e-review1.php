<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Appeal\AppealCase;
use Sabri\CF02\Appeal\AppealDossier;
use Sabri\CF02\Appeal\AppealEligibilityPolicy;
use Sabri\CF02\Appeal\ReviewerAssignmentPolicy;
use Sabri\CF02\Appeal\ReviewerProfile;
use Sabri\CF02\Domain\SupportCaseId;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('self review and prior involvement are blocked', static function (): void {
    $result = (new ReviewerAssignmentPolicy())->assign(
        'DEC-1',
        'reviewer:self',
        'account_action',
        false,
        [new ReviewerProfile('reviewer:self', ['account_action'], ['DEC-1'], [], true, false)]
    );
    assert($result['reviewer_reference'] === null);
});

$test('appeal without standing receives reason and further path', static function (): void {
    $decision = (new AppealEligibilityPolicy())->decide(
        'DEC-2', 'user:2', false,
        new DateTimeImmutable('2026-08-01T00:00:00+05:00'),
        new DateTimeImmutable('2026-08-02T00:00:00+05:00'),
        30, ['policy_misapplied'], false, false, null
    );
    assert(!$decision->eligible());
    assert($decision->furtherPath() !== null);
});

$test('appeal cannot close before implementation', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T03:00:00+05:00');
    $appeal = AppealCase::submit(SupportCaseId::generate(), 'user:3', AppealDossier::create('DEC-3', 'Reason.', 'v1', ['evidence:3'], $at), $at);
    $thrown = false;
    try { $appeal->close($at->modify('+1 minute'), 1); } catch (DomainException) { $thrown = true; }
    assert($thrown);
});

$test('dossier detects silent alteration to original decision', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T03:10:00+05:00');
    $dossier = AppealDossier::create('DEC-4', 'Original reason.', 'v2', ['evidence:4'], $at);
    assert(!$dossier->verifyOriginalIntegrity('DEC-4', 'Changed reason.', 'v2', ['evidence:4']));
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-E first-review regressions passed.\n");
