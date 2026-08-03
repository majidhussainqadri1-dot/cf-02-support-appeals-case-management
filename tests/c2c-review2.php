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
use Sabri\CF02\Escalation\BreachPredictor;
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
    [1 => [['09:00', '17:00']], 2 => [['09:00', '17:00']], 3 => [['09:00', '17:00']], 4 => [['09:00', '17:00']], 5 => [['09:00', '17:00']]]
);

$test('expired assignment decisions and cross-queue transfers are rejected', static function (): void {
    $caseId = SupportCaseId::generate();
    $technicalRequest = new AssignmentRequest($caseId, 'technical', ['technical_support'], 'en-US', 'P3', false, false);
    $technicalDecision = (new AssignmentRouter())->decide(
        $technicalRequest,
        [new AgentProfile('tech-owner', StaffingRole::SupportAgent, ['technical_support'], ['en-US'], ['technical'], 5, 0, true)],
        new DateTimeImmutable('2026-08-04T10:00:00+05:00')
    );

    $expired = false;
    try {
        (new CaseAssignment($caseId))->assign($technicalDecision, 1, new DateTimeImmutable('2026-08-04T10:06:00+05:00'));
    } catch (DomainException) {
        $expired = true;
    }
    assert($expired);

    $assignment = new CaseAssignment($caseId);
    $assignment->assign($technicalDecision, 1, new DateTimeImmutable('2026-08-04T10:01:00+05:00'));

    $publishingRequest = new AssignmentRequest($caseId, 'publishing', ['publishing_support'], 'en-US', 'P3', false, false);
    $publishingDecision = (new AssignmentRouter())->decide(
        $publishingRequest,
        [new AgentProfile('publisher', StaffingRole::SupportAgent, ['publishing_support'], ['en-US'], ['publishing'], 5, 0, true)],
        new DateTimeImmutable('2026-08-04T10:02:00+05:00')
    );

    $crossQueue = false;
    try {
        $assignment->transfer($publishingDecision, 'Improper cross-queue transfer', 2, new DateTimeImmutable('2026-08-04T10:03:00+05:00'));
    } catch (DomainException) {
        $crossQueue = true;
    }
    assert($crossQueue);
});

$test('restricted collaborator access requires explicit purpose-bound approval', static function (): void {
    $caseId = SupportCaseId::generate();
    $request = new AssignmentRequest($caseId, 'privacy_liaison', ['privacy_liaison'], 'en-US', 'P2', true, true);
    $decision = (new AssignmentRouter())->decide(
        $request,
        [new AgentProfile('owner-liaison', StaffingRole::SensitiveLiaison, ['privacy_liaison'], ['en-US'], ['privacy_liaison'], 5, 0, true, true)],
        new DateTimeImmutable('2026-08-04T11:00:00+05:00')
    );
    $assignment = new CaseAssignment($caseId);
    $assignment->assign($decision, 1, new DateTimeImmutable('2026-08-04T11:01:00+05:00'));

    $rejected = false;
    try {
        $assignment->addCollaborator(
            'review-liaison',
            ['restricted_projection'],
            new DateTimeImmutable('2026-08-04T13:00:00+05:00'),
            2,
            new DateTimeImmutable('2026-08-04T11:02:00+05:00')
        );
    } catch (DomainException) {
        $rejected = true;
    }
    assert($rejected);

    assert($assignment->addCollaborator(
        'review-liaison',
        ['restricted_projection'],
        new DateTimeImmutable('2026-08-04T13:00:00+05:00'),
        2,
        new DateTimeImmutable('2026-08-04T11:03:00+05:00'),
        true
    ));
    assert($assignment->canAccess('review-liaison', 'restricted_projection', new DateTimeImmutable('2026-08-04T12:00:00+05:00')));
});

$test('SLA clocks require unique typed evidence for response update pause and resolution', static function () use ($calendar): void {
    $clock = new SlaClock(SlaPolicyRegistry::resolve('technical', 'P2'), $calendar(), new DateTimeImmutable('2026-08-04T09:00:00+05:00'));
    assert($clock->recordFirstResponse(new DateTimeImmutable('2026-08-04T09:15:00+05:00'), 'message:first-1', 1));
    assert(!$clock->recordFirstResponse(new DateTimeImmutable('2026-08-04T09:15:00+05:00'), 'message:first-1', 2));

    $reused = false;
    try {
        $clock->recordUpdate(new DateTimeImmutable('2026-08-04T09:30:00+05:00'), 'message:first-1', 2);
    } catch (DomainException) {
        $reused = true;
    }
    assert($reused);

    assert($clock->recordUpdate(new DateTimeImmutable('2026-08-04T09:30:00+05:00'), 'message:update-1', 2));
    $wrongResolution = false;
    try {
        $clock->resolve(new DateTimeImmutable('2026-08-04T10:00:00+05:00'), 'message:not-resolution', 3);
    } catch (InvalidArgumentException) {
        $wrongResolution = true;
    }
    assert($wrongResolution);
    $clock->resolve(new DateTimeImmutable('2026-08-04T10:00:00+05:00'), 'resolution:case-1-v3', 3);
    assert($clock->status(new DateTimeImmutable('2026-08-04T10:01:00+05:00')) === 'completed');
});

$test('coverage calendars reject overlapping windows and impossible holidays', static function (): void {
    $overlap = false;
    try {
        new CoverageCalendar('coverage.bad.v1', 'Asia/Karachi', [1 => [['09:00', '12:00'], ['11:00', '14:00']]]);
    } catch (InvalidArgumentException) {
        $overlap = true;
    }
    assert($overlap);

    $fakeDate = false;
    try {
        new CoverageCalendar('coverage.bad-date.v1', 'Asia/Karachi', [1 => [['09:00', '12:00']]], ['2026-02-30']);
    } catch (InvalidArgumentException) {
        $fakeDate = true;
    }
    assert($fakeDate);
});

$test('queue health rejects stale and materially future snapshots', static function (): void {
    $evaluator = new QueueHealthEvaluator();
    $now = new DateTimeImmutable('2026-08-04T12:00:00+05:00');
    $stale = new QueueHealthSnapshot('technical', new DateTimeImmutable('2026-08-04T11:00:00+05:00'), 1, 0, 0, 0, 0, 10, 1, 5, 1);
    assert($evaluator->evaluate($stale, $now)->status() === 'critical');

    $future = new QueueHealthSnapshot('technical', new DateTimeImmutable('2026-08-04T12:05:00+05:00'), 1, 0, 0, 0, 0, 10, 1, 5, 1);
    assert($evaluator->evaluate($future, $now)->status() === 'critical');
});

$test('breach prediction rejects stale observation and treats governed pause as watch', static function () use ($calendar): void {
    $clock = new SlaClock(SlaPolicyRegistry::resolve('technical', 'P2'), $calendar(), new DateTimeImmutable('2026-08-04T09:00:00+05:00'));
    $clock->recordFirstResponse(new DateTimeImmutable('2026-08-04T09:10:00+05:00'), 'message:first-predict', 1);
    $clock->pause(SlaPauseReason::AwaitingNativeOwner, 'command:native-1', new DateTimeImmutable('2026-08-04T09:20:00+05:00'), 2);

    $stale = false;
    try {
        (new BreachPredictor())->predict($clock, new DateTimeImmutable('2026-08-04T09:15:00+05:00'), 10, 10);
    } catch (DomainException) {
        $stale = true;
    }
    assert($stale);

    $prediction = (new BreachPredictor())->predict($clock, new DateTimeImmutable('2026-08-04T09:25:00+05:00'), 10, 10);
    assert($prediction->risk() === 'watch');
});

$test('incident public text rejects secrets and projection hides internal notice reference', static function (): void {
    $secret = false;
    try {
        new MajorIncident(
            'CF02-INC-0003',
            'service.media',
            'password: exposed-secret',
            new DateTimeImmutable('2026-08-04T13:00:00+05:00'),
            new DateTimeImmutable('2026-08-04T12:00:00+05:00')
        );
    } catch (InvalidArgumentException) {
        $secret = true;
    }
    assert($secret);

    $case = SupportCaseId::generate();
    $incident = new MajorIncident(
        'CF02-INC-0004',
        'service.media',
        'Media processing is delayed.',
        new DateTimeImmutable('2026-08-04T13:00:00+05:00'),
        new DateTimeImmutable('2026-08-04T12:00:00+05:00')
    );
    $incident->linkCase($case, 'service.media', 'media', 'lead', new DateTimeImmutable('2026-08-04T12:05:00+05:00'), 1);
    $incident->resolve('Media processing has recovered.', 'internal://notice/incident-4', 'lead', new DateTimeImmutable('2026-08-04T12:30:00+05:00'), 2);
    $projection = $incident->projectionForCase($case);
    assert($projection !== null);
    $json = json_encode($projection, JSON_THROW_ON_ERROR);
    assert(!str_contains($json, 'internal://notice'));
    assert($projection['resolution_notice_available'] === true);
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-C second-review adversarial tests passed.\n");
