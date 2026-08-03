<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Audit\TamperEvidentAudit;
use Sabri\CF02\Authorization\AccessContext;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Integration\CommandLedger;
use Sabri\CF02\Integration\ImplementationReconciler;
use Sabri\CF02\Integration\NativeOwnerCommand;
use Sabri\CF02\Search\AuthorizedCaseSearch;
use Sabri\CF02\Search\CaseSearchDocument;
use Sabri\CF02\Security\MutationEnvelope;
use Sabri\CF02\Security\ReplayGuard;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('command ledger rejects same key for altered payload', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T01:00:00+05:00');
    $one = MutationEnvelope::create('idem-adversarial-001', 'agent:1', 'appeal.review', 1, $at, ['state' => 'restore']);
    $two = MutationEnvelope::create('idem-adversarial-001', 'agent:1', 'appeal.review', 1, $at, ['state' => 'remove']);
    $ledger = new CommandLedger(new ReplayGuard());
    $ledger->register(NativeOwnerCommand::create($caseId, 'file-21', 'restore_content', 'content:1', ['state' => 'restore'], 1, $one, $at), $at);
    $thrown = false;
    try { $ledger->register(NativeOwnerCommand::create($caseId, 'file-21', 'remove_content', 'content:1', ['state' => 'remove'], 1, $two, $at), $at); }
    catch (DomainException) { $thrown = true; }
    assert($thrown);
});

$test('reconciliation exposes owner and version drift', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T01:10:00+05:00');
    $env = MutationEnvelope::create('idem-adversarial-002', 'agent:1', 'appeal.review', 1, $at, ['state' => 'restore']);
    $command = NativeOwnerCommand::create($caseId, 'file-21', 'restore_content', 'content:1', ['state' => 'restore'], 4, $env, $at);
    $command->markDispatched('provider:1', $at->modify('+1 minute'), 1);
    $command->markSucceeded('native:1', 4, $at->modify('+2 minutes'), 2);
    $result = (new ImplementationReconciler())->reconcile($command, 'file-18', 'restore_content', 'content:1', 3, 'native:1', $at->modify('+3 minutes'));
    assert($result['status'] === 'drift');
    assert(count($result['reasons']) >= 2);
});

$test('search cursor tampering fails safely without hidden counts', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T01:20:00+05:00');
    $context = new AccessContext('agent:1', ['support_agent'], ['case.search'], [$caseId->value()], ['technical'], 'case.support', $at, $at->modify('+1 hour'), null, false);
    $document = new CaseSearchDocument($caseId, 'technical', 'technical', 'P2', 'new', 'en-US', 'Safe subject', ['agent:1'], $at, 1);
    $thrown = false;
    try { (new AuthorizedCaseSearch())->search($context, [$document], 'safe', null, null, 20, 'tampered'); }
    catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('audit rejects sensitive context key before persistence', static function (): void {
    $thrown = false;
    try {
        (new TamperEvidentAudit())->createEvent('case', 'case:1', 'agent:1', 'case.support', 'view', 'allowed', 'tr_' . str_repeat('d', 32), 1, ['password' => 'secret'], new DateTimeImmutable(), null);
    } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-D second-review adversarial tests passed.\n");
