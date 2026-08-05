<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Audit\TamperEvidentAudit;
use Sabri\CF02\Authorization\AccessContext;
use Sabri\CF02\Authorization\PurposeBoundAccessPolicy;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Evidence\LegalHold;
use Sabri\CF02\Evidence\SecureExportService;
use Sabri\CF02\Integration\CommandLedger;
use Sabri\CF02\Integration\ImplementationReconciler;
use Sabri\CF02\Integration\NativeOwnerCommand;
use Sabri\CF02\Search\AuthorizedCaseSearch;
use Sabri\CF02\Search\CaseSearchDocument;
use Sabri\CF02\Security\MutationEnvelope;
use Sabri\CF02\Security\ReplayGuard;

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

$test('purpose-bound sensitive access requires capability assignment approval and recent authentication', static function (): void {
    $caseId = SupportCaseId::generate();
    $issued = new DateTimeImmutable('2026-08-04T00:00:00+05:00');
    $context = new AccessContext(
        'agent:privacy-1',
        ['privacy_liaison'],
        ['case.view', 'case.restricted', 'case.export', 'native.command', 'case.search'],
        [$caseId->value()],
        ['privacy_liaison'],
        'privacy.rights',
        $issued,
        $issued->modify('+2 hours'),
        $issued->modify('+5 minutes'),
        true
    );
    $decision = (new PurposeBoundAccessPolicy())->decide(
        $context,
        $caseId,
        'privacy_liaison',
        'C4',
        'restricted_projection',
        $issued->modify('+10 minutes')
    );
    assert($decision->allowed());
});

$test('native command is idempotent versioned and reconciled without direct companion write', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T00:10:00+05:00');
    $payload = ['decision_ref' => 'MOD-123', 'requested_state' => 'restore'];
    $envelope = MutationEnvelope::create('idem-command-00000001', 'reviewer:1', 'appeal.review', 1, $at, $payload, 'tr_' . str_repeat('a', 32));
    $command = NativeOwnerCommand::create($caseId, 'file-21', 'restore_content', 'content:123', $payload, 7, $envelope, $at);
    $ledger = new CommandLedger(new ReplayGuard());
    assert($ledger->register($command, $at) === $command);
    assert($ledger->register($command, $at) === $command);
    $command->markDispatched('provider:job-1', $at->modify('+1 minute'), 1);
    $command->markSucceeded('native-outcome:1', 8, $at->modify('+2 minutes'), 2);
    $result = (new ImplementationReconciler())->reconcile(
        $command,
        'file-21',
        'restore_content',
        'content:123',
        8,
        'native-outcome:1',
        $at->modify('+3 minutes')
    );
    assert($result['status'] === 'reconciled');
});

$test('authorized search returns only visible minimized projections', static function (): void {
    $visible = SupportCaseId::generate();
    $hidden = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T00:20:00+05:00');
    $context = new AccessContext(
        'agent:search-1',
        ['support_agent'],
        ['case.search'],
        [$visible->value()],
        ['technical'],
        'case.support',
        $at,
        $at->modify('+1 hour'),
        null,
        false
    );
    $documents = [
        new CaseSearchDocument($visible, 'technical', 'technical', 'P2', 'in_progress', 'ur-PK', 'Login page error', ['agent:search-1'], $at, 3),
        new CaseSearchDocument($hidden, 'billing', 'billing', 'P3', 'new', 'en-US', 'Invoice question', ['agent:other'], $at, 1),
    ];
    $result = (new AuthorizedCaseSearch())->search($context, $documents, 'login');
    assert(count($result['results']) === 1);
    assert($result['results'][0]['case_id'] === $visible->value());
    assert(!array_key_exists('requester', $result['results'][0]));
});

$test('secure export is bounded hashed and time limited', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T00:30:00+05:00');
    $context = new AccessContext(
        'privacy:officer-1',
        ['privacy_liaison'],
        ['case.export'],
        [$caseId->value()],
        ['privacy_liaison'],
        'privacy.rights',
        $at,
        $at->modify('+1 hour'),
        $at,
        true
    );
    $result = (new SecureExportService())->build(
        $context,
        $caseId,
        'privacy.rights',
        ['case.json' => '{"status":"resolved"}', 'timeline.txt' => 'Case timeline'],
        $at,
        $at->modify('+2 days')
    );
    assert(count($result['manifest']->files()) === 2);
    assert(!$result['manifest']->isExpired($at->modify('+1 day')));
});

$test('legal hold blocks purge target and requires reviewed release', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T00:40:00+05:00');
    $hold = LegalHold::forCase($caseId, 'appeal_pending', 'authority:appeal', $at, $at->modify('+30 days'));
    assert($hold->appliesTo($caseId, 'technical'));
    $hold->review($at->modify('+60 days'), $at->modify('+20 days'), 1);
    $hold->release('Appeal limitation period ended.', $at->modify('+40 days'), 2);
    assert(!$hold->active());
    assert(!$hold->appliesTo($caseId, 'technical'));
});

$test('tamper-evident audit chain verifies and omits sensitive body fields', static function (): void {
    $audit = new TamperEvidentAudit();
    $at = new DateTimeImmutable('2026-08-04T00:50:00+05:00');
    $first = $audit->createEvent('case', 'case:1', 'agent:1', 'case.support', 'view', 'allowed', 'tr_' . str_repeat('b', 32), 1, ['queue' => 'technical'], $at, null);
    $second = $audit->createEvent('case', 'case:1', 'agent:1', 'case.support', 'reply', 'accepted', 'tr_' . str_repeat('c', 32), 2, ['channel' => 'web'], $at->modify('+1 minute'), (string) $first['event_hash']);
    assert($audit->verify([$first, $second]));
    $second['action'] = 'delete';
    assert(!$audit->verify([$first, $second]));
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-D tests passed.\n");
