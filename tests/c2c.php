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
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Escalation\BreachPredictor;
use Sabri\CF02\Escalation\EscalationPolicy;
use Sabri\CF02\Incident\IncidentStatus;
use Sabri\CF02\Incident\MajorIncident;
use Sabri\CF02\Queue\QueueHealthEvaluator;
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
    [
        1 => [['09:00', '17:00']],
        2 => [['09:00', '17:00']],
        3 => [['09:00', '17:00']],
        4 => [['09:00', '17:00']],
        5 => [['09:00', '17:00']],
    ]
);

$test('assignment chooses eligible low-load language-matching agent', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'ur-PK', 'P2', false, false);
    $agents = [
        new AgentProfile('agent-b', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 10, 1, true),
        new AgentProfile('agent-a', StaffingRole::SupportAgent, ['technical_support'], ['ur-PK'], ['technical'], 10, 3, true),
        new AgentProfile('agent-c', StaffingRole::SupportAgent, ['technical_support'], ['ur-PK'], ['technical'], 10, 9, true),
    ];
    $decision = (new AssignmentRouter())->decide($request, $agents, new DateTimeImmutable('2026-08-03T12:00:00+05:00'));
    assert($decision->isAssigned());
    assert($decision->agentReference() === 'agent-a');
    assert($decision->eligibleCandidates() === 3);
});

$test('sensitive assignment fails closed without cleared liaison', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'privacy_liaison', ['privacy_liaison'], 'en-US', 'P2', true, true);
    $agents = [new AgentProfile('ordinary', StaffingRole::SpecialistAgent, ['privacy_liaison'], ['en-US'], ['privacy_liaison'], 5, 0, true)];
    $decision = (new AssignmentRouter())->decide($request, $agents);
    assert(!$decision->isAssigned());
});

$test('accountable owner transfer revokes old owner and preserves one owner', static function (): void {
    $caseId = SupportCaseId::generate();
    $router = new AssignmentRouter();
    $request = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'en-US', 'P3', false, false);
    $first = $router->decide($request, [new AgentProfile('agent-1', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true)], new DateTimeImmutable('2026-08-03T12:00:00+05:00'));
    $second = $router->decide($request, [new AgentProfile('agent-2', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true)], new DateTimeImmutable('2026-08-03T13:00:00+05:00'));

    $assignment = new CaseAssignment($caseId);
    $assignment->assign($first, 1);
    $assignment->addCollaborator('agent-3', ['view_case'], new DateTimeImmutable('2026-08-04T12:00:00+05:00'), 2, new DateTimeImmutable('2026-08-03T12:30:00+05:00'));
    assert($assignment->canAccess('agent-1', 'reply_case'));
    assert($assignment->canAccess('agent-3', 'view_case', new DateTimeImmutable('2026-08-03T13:00:00+05:00')));
    $assignment->transfer($second, 'Required workload rebalance', 3, new DateTimeImmutable('2026-08-03T13:00:00+05:00'));
    assert($assignment->ownerReference() === 'agent-2');
    assert(!$assignment->canAccess('agent-1', 'reply_case'));
});

$test('assignment aggregate rejects stale version', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'en-US', 'P3', false, false);
    $decision = (new AssignmentRouter())->decide($request, [new AgentProfile('agent-1', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true)]);
    $assignment = new CaseAssignment($caseId);
    $assignment->assign($decision, 1);
    $thrown = false;
    try {
        $assignment->addCollaborator('agent-2', ['view_case'], new DateTimeImmutable('+1 day'), 1);
    } catch (ConcurrencyConflict) {
        $thrown = true;
    }
    assert($thrown);
});

$test('coverage calendar carries working minutes across weekend', static function () use ($calendar): void {
    $deadline = $calendar()->addWorkingMinutes(new DateTimeImmutable('2026-08-07T16:30:00+05:00'), 60);
    assert($deadline->format('Y-m-d H:i') === '2026-08-10 09:30');
});

$test('SLA registry and governed pause law validate', static function () use ($calendar): void {
    assert(SlaPolicyRegistry::validate() === []);
    $policy = SlaPolicyRegistry::resolve('technical', 'P2');
    $clock = new SlaClock($policy, $calendar(), new DateTimeImmutable('2026-08-03T09:00:00+05:00'));

    $rejected = false;
    try {
        $clock->pause(SlaPauseReason::AwaitingRequester, 'message:request-1', new DateTimeImmutable('2026-08-03T09:20:00+05:00'), 1);
    } catch (DomainException) {
        $rejected = true;
    }
    assert($rejected);

    $clock->recordFirstResponse(new DateTimeImmutable('2026-08-03T09:30:00+05:00'), 1);
    $before = $clock->resolutionDeadline();
    $clock->pause(SlaPauseReason::AwaitingRequester, 'message:request-1', new DateTimeImmutable('2026-08-03T10:00:00+05:00'), 2);
    $paused = $clock->resume(new DateTimeImmutable('2026-08-03T11:00:00+05:00'), 3);
    assert($paused === 60);
    assert($clock->resolutionDeadline() > $before);
});

$test('breach prediction and escalation preserve P1 oversight', static function () use ($calendar): void {
    $clock = new SlaClock(SlaPolicyRegistry::resolve('technical', 'P1'), $calendar(), new DateTimeImmutable('2026-08-03T09:00:00+05:00'));
    $prediction = (new BreachPredictor())->predict($clock, new DateTimeImmutable('2026-08-03T09:30:00+05:00'), 180, 60);
    assert(in_array($prediction->risk(), ['high', 'breach'], true));
    $decision = (new EscalationPolicy())->decide('P1', false, false, $prediction, 0, false);
    assert(in_array($decision->level(), ['specialist', 'incident_command'], true));
    assert($decision->humanApprovalRequired());
});

$test('queue health becomes critical for breached or unowned P1 work', static function (): void {
    $snapshot = new QueueHealthSnapshot('technical', new DateTimeImmutable(), 10, 2, 1, 3, 1, 1500, 2, 10, 9);
    $decision = (new QueueHealthEvaluator())->evaluate($snapshot);
    assert($decision->status() === 'critical');
});

$test('major incident links cases without merging or cross-case disclosure', static function (): void {
    $caseA = SupportCaseId::generate();
    $caseB = SupportCaseId::generate();
    $incident = new MajorIncident('CF02-INC-0001', 'service.support-api', 'Support submissions are delayed.', new DateTimeImmutable('+1 hour'));
    assert($incident->linkCase($caseA, 'service.support-api', 'technical', 'lead-1', new DateTimeImmutable(), 1));
    assert($incident->linkCase($caseB, 'service.support-api', 'technical', 'lead-1', new DateTimeImmutable(), 2));
    $projection = $incident->projectionForCase($caseA);
    assert($projection !== null);
    assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), $caseB->value()));
    assert($incident->linkedCaseCount() === 2);
    $incident->markMonitoring(new DateTimeImmutable('+2 hours'), 'lead-1', 3);
    assert($incident->status() === IncidentStatus::Monitoring);
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-C foundation tests passed.\n");
