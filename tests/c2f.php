<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Automation\AutomationAction;
use Sabri\CF02\Automation\AutomationGuard;
use Sabri\CF02\Configuration\ConfigurationRegistry;
use Sabri\CF02\Configuration\ConfigurationSnapshot;
use Sabri\CF02\Delivery\DeliveryOutbox;
use Sabri\CF02\Delivery\OutboxMessage;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Evidence\LegalHold;
use Sabri\CF02\Feedback\SatisfactionAggregator;
use Sabri\CF02\Feedback\SatisfactionFeedback;
use Sabri\CF02\Knowledge\KnowledgeArticle;
use Sabri\CF02\Knowledge\KnowledgeSuggestionService;
use Sabri\CF02\Quality\QualityReview;
use Sabri\CF02\Retention\PurgePlanner;
use Sabri\CF02\Retention\RetentionPolicy;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
};

$test('automation only provides marked suggestions and blocks final authority', static function (): void {
    $guard = new AutomationGuard();
    $draft = $guard->decide(AutomationAction::DraftReply, 0.95, 'model-2026-08', false);
    assert($draft['allowed']);
    assert($draft['human_review_required']);
    $appeal = $guard->decide(AutomationAction::FinalAppealDecision, 1.0, 'model-2026-08', false);
    assert(!$appeal['allowed']);
    assert($appeal['native_owner_required']);
});

$test('quality review uses complete rubric and tracks correction without punitive surveillance', static function (): void {
    $review = QualityReview::create(
        SupportCaseId::generate(),
        'quality:1',
        'risk',
        ['accuracy' => 90, 'empathy' => 88, 'compliance' => 95, 'security' => 92, 'accessibility' => 85],
        ['Clarify the next step in plain language.'],
        new DateTimeImmutable('2026-08-04T05:00:00+05:00'),
        true
    );
    assert(!$review->requiresCorrection());
    assert($review->identitySuppressed());
    $review->recordCorrection('case-correction:1');
    assert($review->correctionReference() === 'case-correction:1');
});

$test('knowledge suggestions exclude expired or unsafe articles and cannot execute actions', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T05:10:00+05:00');
    $articles = [
        new KnowledgeArticle('KB-TECH-1', 'Clear browser cache', 'owner:help', 'source:runbook-1', 'v3', ['technical'], 'Please clear the browser cache and retry.', $at->modify('-1 day'), $at->modify('+30 days'), true, true),
        new KnowledgeArticle('KB-TECH-2', 'Old article', 'owner:help', 'source:runbook-2', 'v1', ['technical'], 'Old draft.', $at->modify('-1 year'), $at->modify('-1 day'), true, true),
    ];
    $suggestions = (new KnowledgeSuggestionService())->suggest('technical', $articles, $at);
    assert(count($suggestions) === 1);
    assert(str_contains($suggestions[0]['execution_boundary'], 'Suggestion only'));
});

$test('satisfaction reporting suppresses low-volume identities', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T05:20:00+05:00');
    $low = [new SatisfactionFeedback($caseId, 'p1', 5, null, false, $at)];
    assert((new SatisfactionAggregator())->aggregate($low, 3)['suppressed']);
    $cohort = [];
    foreach (range(1, 5) as $i) {
        $cohort[] = new SatisfactionFeedback($caseId, 'p' . $i, $i === 5 ? 4 : 5, null, false, $at);
    }
    $report = (new SatisfactionAggregator())->aggregate($cohort, 5);
    assert(!$report['suppressed']);
    assert($report['response_count'] === 5);
});

$test('configuration supports preview staging activation and rollback with approvals', static function (): void {
    $at = new DateTimeImmutable('2026-08-04T05:30:00+05:00');
    $registry = new ConfigurationRegistry();
    $v1 = ConfigurationSnapshot::create('CF02-CFG-RUNTIME', 1, 'staged', [
        'categories' => ['technical'],
        'forms' => ['technical' => ['subject']],
        'slas' => ['technical.P2' => 1440],
        'queues' => ['technical'],
        'skills' => ['technical_support'],
        'templates' => ['receipt' => 'Case {{case_id}} received'],
        'escalation' => ['P1' => 'incident_command'],
        'feature_flags' => ['runtime' => false],
    ], ['approver:1', 'approver:2'], 'creator:1', $at);
    $registry->stage($v1, true);
    assert($registry->activate(1, $at->modify('+1 minute'))->status() === 'active');
});

$test('degraded delivery preserves one truth and prevents duplicate sends', static function (): void {
    $caseId = SupportCaseId::generate();
    $at = new DateTimeImmutable('2026-08-04T05:40:00+05:00');
    $outbox = new DeliveryOutbox();
    $message = OutboxMessage::create($caseId, 'email', 'user:1', 'case.receipt', 'safe payload', 'outbox-idem-0000001', $at, true);
    assert($outbox->enqueue($message) === $message);
    assert($outbox->enqueue($message) === $message);
    $ready = $outbox->ready($at);
    assert(count($ready) === 1);
    $message->markAttempt($at);
    $message->markFailed(true);
    assert($message->status() === 'queued');
});

$test('retention respects active hold and preserves minimal decision evidence', static function (): void {
    $caseId = SupportCaseId::generate();
    $closedAt = new DateTimeImmutable('2025-01-01T00:00:00+05:00');
    $at = new DateTimeImmutable('2026-08-04T05:50:00+05:00');
    $policy = new RetentionPolicy('technical', 180, 365, 90, 730, true);
    $hold = LegalHold::forCase($caseId, 'appeal_pending', 'authority:1', $at->modify('-1 day'), $at->modify('+30 days'));
    $held = (new PurgePlanner())->plan($caseId, 'technical', 'closed', $closedAt, $policy, [$hold], $at);
    assert($held['action'] === 'hold');
    $purge = (new PurgePlanner())->plan($caseId, 'technical', 'closed', $closedAt, $policy, [], $at);
    assert($purge['action'] === 'purge');
    assert(in_array('minimal_decision_evidence', $purge['preserve'], true));
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-F tests passed.\n");
