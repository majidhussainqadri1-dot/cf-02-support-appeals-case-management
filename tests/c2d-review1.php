<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Authorization\AccessContext;
use Sabri\CF02\Authorization\PurposeBoundAccessPolicy;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Evidence\SecureExportService;
use Sabri\CF02\Security\MutationEnvelope;
use Sabri\CF02\Security\ReplayGuard;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('expired or suspended context fails closed', static function (): void {
    $caseId = SupportCaseId::generate();
    $issued = new DateTimeImmutable('2026-08-04T00:00:00+05:00');
    $context = new AccessContext('agent:1', ['support_agent'], ['case.view'], [$caseId->value()], ['technical'], 'case.support', $issued, $issued->modify('+5 minutes'), null, false, true);
    $decision = (new PurposeBoundAccessPolicy())->decide($context, $caseId, 'technical', 'C3', 'view_case', $issued->modify('+1 minute'));
    assert(!$decision->allowed());
});

$test('sensitive field requires recent authentication and specialist approval', static function (): void {
    $caseId = SupportCaseId::generate();
    $issued = new DateTimeImmutable('2026-08-04T00:00:00+05:00');
    $context = new AccessContext('agent:privacy', ['privacy_liaison'], ['case.restricted'], [$caseId->value()], ['privacy_liaison'], 'privacy.rights', $issued, $issued->modify('+2 hours'), $issued, false);
    $decision = (new PurposeBoundAccessPolicy())->decide($context, $caseId, 'privacy_liaison', 'C4', 'restricted_projection', $issued->modify('+10 minutes'));
    assert(!$decision->allowed());
});

$test('replay key cannot bind two payloads', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T00:00:00+05:00');
    $guard = new ReplayGuard();
    $first = MutationEnvelope::create('idem-review1-000001', 'agent:1', 'case.support', 1, $at, ['a' => 1]);
    $second = MutationEnvelope::create('idem-review1-000001', 'agent:1', 'case.support', 1, $at, ['a' => 2]);
    assert($guard->accept($first, $at));
    $thrown = false;
    try { $guard->accept($second, $at); } catch (DomainException) { $thrown = true; }
    assert($thrown);
});

$test('secure export blocks seeded credentials', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T00:00:00+05:00');
    $context = new AccessContext('privacy:1', ['privacy_liaison'], ['case.export'], [$caseId->value()], ['privacy_liaison'], 'privacy.rights', $at, $at->modify('+1 hour'), $at, true);
    $thrown = false;
    try {
        (new SecureExportService())->build($context, $caseId, 'privacy.rights', ['case.txt' => 'password: hunter2'], $at, $at->modify('+1 day'));
    } catch (DomainException) { $thrown = true; }
    assert($thrown);
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-D first-review regressions passed.\n");
