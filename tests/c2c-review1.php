<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Assignment\AgentProfile;
use Sabri\CF02\Assignment\AssignmentRequest;
use Sabri\CF02\Assignment\AssignmentRouter;
use Sabri\CF02\Assignment\CaseAssignment;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Incident\MajorIncident;
use Sabri\CF02\Queue\QueueHealthSnapshot;
use Sabri\CF02\Sla\CoverageCalendar;
use Sabri\CF02\Sla\SlaClock;
use Sabri\CF02\Sla\SlaPauseReason;
use Sabri\CF02\Sla\SlaPolicyRegistry;
use Sabri\CF02\Staffing\StaffingRole;

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

$calendar = static fn (): CoverageCalendar => new CoverageCalendar(
    'coverage.technical.v1',
    'Asia/Karachi',
    [1 => [['09:00', '17:00']], 2 => [['09:00', '17:00']], 3 => [['09:00', '17:00']], 4 => [['09:00', '17:00']], 5 => [['09:00', '17:00']]]
);

$test('assignment fails closed on language mismatch and malformed candidates', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'ur-PK', 'P3', false, false);
    $decision = (new AssignmentRouter())->decide($request, [
        new AgentProfile('agent-en', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true),
    ]);
    assert(!$decision->isAssigned());

    $thrown = false;
    try {
        (new AssignmentRouter())->decide($request, ['not-an-agent']);
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    assert($thrown);
});

$test('restricted projection requires a cleared sensitive assignment', static function (): void {
    $ordinaryCase = SupportCaseId::generate();
    $ordinaryRequest = new AssignmentRequest($ordinaryCase, 'technical', ['technical_support'], 'en-US', 'P3', false, false);
    $ordinaryDecision = (new AssignmentRouter())->decide($ordinaryRequest, [
        new AgentProfile('ordinary', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true),
    ]);
    $ordinaryAssignment = new CaseAssignment($ordinaryCase);
    $ordinaryAssignment->assign($ordinaryDecision, 1);
    assert(!$ordinaryAssignment->canAccess('ordinary', 'restricted_projection'));

    $sensitiveCase = SupportCaseId::generate();
    $sensitiveRequest = new AssignmentRequest($sensitiveCase, 'privacy_liaison', ['privacy_liaison'], 'en-US', 'P2', true, true);
    $sensitiveDecision = (new AssignmentRouter())->decide($sensitiveRequest, [
        new AgentProfile('liaison', StaffingRole::SensitiveLiaison, ['privacy_liaison'], ['en-US'], ['privacy_liaison'], 5, 0, true, true),
    ]);
    $sensitiveAssignment = new CaseAssignment($sensitiveCase);
    $sensitiveAssignment->assign($sensitiveDecision, 1);
    assert($sensitiveAssignment->canAccess('liaison', 'restricted_projection'));
});

$test('collaborator grant cannot be silently widened or extended', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'en-US', 'P3', false, false);
    $decision = (new AssignmentRouter())->decide($request, [new AgentProfile('owner', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true)]);
    $assignment = new CaseAssignment($caseId);
    $assignment->assign($decision, 1);
    $expiry = new DateTimeImmutable('2026-08-05T10:00:00+05:00');
    assert($assignment->addCollaborator('helper', ['view_case'], $expiry, 2, new DateTimeImmutable('2026-08-04T10:00:00+05:00')));
    assert(!$assignment->addCollaborator('helper', ['view_case'], $expiry, 3, new DateTimeImmutable('2026-08-04T10:01:00+05:00')));

    $thrown = false;
    try {
        $assignment->addCollaborator('helper', ['view_case', 'reply_case'], $expiry, 3, new DateTimeImmutable('2026-08-04T10:02:00+05:00'));
    } catch (DomainException) {
        $thrown = true;
    }
    assert($thrown);
});

$test('SLA pause cannot hide first-response or existing breach and resolution requires response', static function () use ($calendar): void {
    $clock = new SlaClock(SlaPolicyRegistry::resolve('technical', 'P2'), $calendar(), new DateTimeImmutable('2026-08-03T09:00:00+05:00'));

    $pauseBeforeResponse = false;
    try {
        $clock->pause(SlaPauseReason::AwaitingNativeOwner, 'command:1', new DateTimeImmutable('2026-08-03T09:10:00+05:00'), 1);
    } catch (DomainException) {
        $pauseBeforeResponse = true;
    }
    assert($pauseBeforeResponse);

    $resolveBeforeResponse = false;
    try {
        $clock->resolve(new DateTimeImmutable('2026-08-03T09:20:00+05:00'), 1);
    } catch (DomainException) {
        $resolveBeforeResponse = true;
    }
    assert($resolveBeforeResponse);

    $clock->recordFirstResponse(new DateTimeImmutable('2026-08-03T09:30:00+05:00'), 1);
    $breachedPause = false;
    try {
        $clock->pause(SlaPauseReason::AwaitingNativeOwner, 'command:2', new DateTimeImmutable('2026-08-04T15:00:00+05:00'), 2);
    } catch (DomainException) {
        $breachedPause = true;
    }
    assert($breachedPause);
});

$test('queue health rejects impossible capacity and empty-queue metrics', static function (): void {
    $badCapacity = false;
    try {
        new QueueHealthSnapshot('technical', new DateTimeImmutable(), 2, 0, 0, 0, 0, 10, 3, 2, 1);
    } catch (InvalidArgumentException) {
        $badCapacity = true;
    }
    assert($badCapacity);

    $badEmpty = false;
    try {
        new QueueHealthSnapshot('technical', new DateTimeImmutable(), 0, 0, 0, 0, 0, 10, 0, 0, 0);
    } catch (InvalidArgumentException) {
        $badEmpty = true;
    }
    assert($badEmpty);
});

$test('incident timeline rejects backdating and non-future monitoring update', static function (): void {
    $opened = new DateTimeImmutable('2026-08-03T18:00:00+05:00');
    $incident = new MajorIncident('CF02-INC-0002', 'service.search', 'Search responses are delayed.', new DateTimeImmutable('2026-08-03T19:00:00+05:00'), $opened);
    $case = SupportCaseId::generate();
    $incident->linkCase($case, 'service.search', 'technical', 'lead', new DateTimeImmutable('2026-08-03T18:10:00+05:00'), 1);

    $backdated = false;
    try {
        $incident->unlinkCase($case, 'wrong link', 'lead', new DateTimeImmutable('2026-08-03T18:05:00+05:00'), 2);
    } catch (DomainException) {
        $backdated = true;
    }
    assert($backdated);

    $notFuture = false;
    try {
        $incident->markMonitoring(new DateTimeImmutable('2026-08-03T18:10:00+05:00'), 'lead', new DateTimeImmutable('2026-08-03T18:10:00+05:00'), 2);
    } catch (InvalidArgumentException) {
        $notFuture = true;
    }
    assert($notFuture);
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-C first-review regressions passed.\n");
