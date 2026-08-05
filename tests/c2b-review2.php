<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Attachment\AttachmentRecord;
use Sabri\CF02\Attachment\AttachmentScanResult;
use Sabri\CF02\Attachment\AttachmentState;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseWorkspace;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Intake\IdempotencyKey;
use Sabri\CF02\Intake\IntakeChannel;
use Sabri\CF02\Intake\IntakeLedger;
use Sabri\CF02\Intake\IntakeRequest;
use Sabri\CF02\Intake\TriagePolicy;
use Sabri\CF02\Portal\UserCaseProjection;
use Sabri\CF02\Resolution\ResolutionCode;
use Sabri\CF02\Resolution\ResolutionDecision;
use Sabri\CF02\Security\SensitiveContentDetector;
use Sabri\CF02\Thread\CaseMessage;
use Sabri\CF02\Thread\MessageVisibility;

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); fwrite(STDOUT, "PASS {$name}\n"); }
    catch (Throwable $exception) { $failures[] = $name . ': ' . $exception->getMessage(); fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n"); }
};

$request = static fn (string $description = 'A repeatable technical issue occurs on the support page.'): IntakeRequest => new IntakeRequest(
    'user|one', IntakeChannel::Web, 'message|one', true, 'technical', $description,
    ['route' => '/support', 'device_context' => 'Chrome', 'reproduction_steps' => 'Open, submit and observe the error.'],
    'medium', 'normal', 'en-US'
);

$test('delimiter-shaped identities cannot collide in canonical idempotency encoding', static function (): void {
    $first = IdempotencyKey::forIntake(IntakeChannel::Web, 'a|b', 'c');
    $second = IdempotencyKey::forIntake(IntakeChannel::Web, 'a', 'b|c');
    assert($first->value() !== $second->value());
});

$test('intake ledger returns one case for exact replay and rejects changed payload', static function () use ($request): void {
    $ledger = new IntakeLedger();
    $caseId = SupportCaseId::generate();
    $otherCase = SupportCaseId::generate();
    assert($ledger->register($request(), $caseId)->equals($caseId));
    assert($ledger->register($request(), $otherCase)->equals($caseId));
    assert($ledger->count() === 1);

    $changed = $request('A changed technical description reuses the same source identity.');
    $thrown = false;
    try { $ledger->register($changed, SupportCaseId::generate()); } catch (DomainException) { $thrown = true; }
    assert($thrown);
});

$test('direct scanned-state transition is forbidden without scan evidence', static function (): void {
    $attachment = new AttachmentRecord('scan-1', SupportCaseId::generate(), hash('sha256', 'scan'), 'text/plain', 10, 'evidence', new DateTimeImmutable(), 'private://scan-1', 'C2');
    $attachment->transition(AttachmentState::Quarantined);
    $thrown = false;
    try { $attachment->transition(AttachmentState::Scanned); } catch (DomainException) { $thrown = true; }
    assert($thrown);
    assert($attachment->state() === AttachmentState::Quarantined);
});

$test('scan hash mismatch and scanner error preserve quarantine', static function (): void {
    $hash = hash('sha256', 'scan');
    $attachment = new AttachmentRecord('scan-2', SupportCaseId::generate(), $hash, 'text/plain', 10, 'evidence', new DateTimeImmutable(), 'private://scan-2', 'C2');
    $attachment->transition(AttachmentState::Quarantined);

    $mismatch = false;
    try { $attachment->recordScan(new AttachmentScanResult(hash('sha256', 'other'), 'text/plain', 'clean', 'scanner', '1.0.0', new DateTimeImmutable())); }
    catch (DomainException) { $mismatch = true; }
    assert($mismatch);
    assert($attachment->state() === AttachmentState::Quarantined);

    $error = false;
    try { $attachment->recordScan(new AttachmentScanResult($hash, 'text/plain', 'error', 'scanner', '1.0.0', new DateTimeImmutable())); }
    catch (DomainException) { $error = true; }
    assert($error);
    assert($attachment->state() === AttachmentState::Quarantined);
});

$test('infected or MIME-mismatched scan is rejected', static function (): void {
    $hash = hash('sha256', 'scan');
    $infected = new AttachmentRecord('scan-3', SupportCaseId::generate(), $hash, 'text/plain', 10, 'evidence', new DateTimeImmutable(), 'private://scan-3', 'C2');
    $infected->transition(AttachmentState::Quarantined);
    $infected->recordScan(new AttachmentScanResult($hash, 'text/plain', 'infected', 'scanner', '1.0.0', new DateTimeImmutable()));
    assert($infected->state() === AttachmentState::Rejected);

    $mismatch = new AttachmentRecord('scan-4', SupportCaseId::generate(), $hash, 'text/plain', 10, 'evidence', new DateTimeImmutable(), 'private://scan-4', 'C2');
    $mismatch->transition(AttachmentState::Quarantined);
    $mismatch->recordScan(new AttachmentScanResult($hash, 'application/pdf', 'clean', 'scanner', '1.0.0', new DateTimeImmutable()));
    assert($mismatch->state() === AttachmentState::Rejected);
});

$test('acute safety indicator requires explicit emergency diversion', static function (): void {
    $request = new IntakeRequest('user-2', IntakeChannel::Web, 'safety-1', true, 'safety_abuse',
        'There is an immediate danger indicator requiring safe local direction.',
        ['report_type' => 'acute safety concern', 'subject_reference' => 'subject-1', 'immediacy' => 'emergency'],
        'critical', 'immediate', 'en-US');
    $decision = (new TriagePolicy())->decide($request);
    assert($decision->emergencyDiversionRequired());
    assert($decision->humanReviewRequired());
});

$test('expired reopen window blocks reopening and direct transition cannot bypass it', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $case->transition(CaseState::Triaged, 1);
    $case->transition(CaseState::InProgress, 2);
    $decision = new ResolutionDecision(ResolutionCode::InformationProvided, ['Provided instructions'], null,
        'Review the supplied instructions.', true, new DateTimeImmutable('2026-08-02T00:00:00+05:00'), true, true);

    $policyBlocked = false;
    try { $case->resolve($decision, [], 3, new DateTimeImmutable('2026-08-03T00:00:00+05:00')); }
    catch (DomainException) { $policyBlocked = true; }
    assert($policyBlocked);

    $direct = false;
    try { $case->transition(CaseState::Reopened, 3); } catch (DomainException) { $direct = true; }
    assert($direct);
});

$test('closed case cannot be mutated until governed reopen', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $case->transition(CaseState::Triaged, 1);
    $case->transition(CaseState::InProgress, 2);
    $decision = new ResolutionDecision(ResolutionCode::InformationProvided, ['Provided instructions'], null,
        'Review the supplied instructions.', true, new DateTimeImmutable('+7 days'), true, true);
    $case->resolve($decision, [], 3, new DateTimeImmutable());
    $case->close(true, 4);

    $blocked = false;
    try {
        $case->appendMessage(new CaseMessage('late', 'user-1', MessageVisibility::Requester, 'A late message should require reopening first.', 'portal', 'late-key', new DateTimeImmutable()), 5);
    } catch (DomainException) { $blocked = true; }
    assert($blocked);

    $case->reopen(5, new DateTimeImmutable());
    assert($case->state() === CaseState::Reopened);
});

$test('user portal replaces raw support-agent references with a safe label', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $message = new CaseMessage('reply-1', 'agent-private-uuid', MessageVisibility::Requester,
        'Support has received the case and is reviewing the bounded evidence.', 'portal', 'reply-key', new DateTimeImmutable());
    $case->appendMessage($message, 1);
    $projection = UserCaseProjection::fromWorkspace($case);
    $encoded = json_encode($projection, JSON_THROW_ON_ERROR);
    assert($projection['messages'][0]['author_label'] === 'Support Team');
    assert(!str_contains($encoded, 'agent-private-uuid'));
});

$test('resolution content rejects secrets and duplicate actions', static function (): void {
    $secret = false;
    try {
        new ResolutionDecision(ResolutionCode::InformationProvided, ['Provided answer'], null,
            'Your OTP: 123456 is recorded here.', true, new DateTimeImmutable('+7 days'), true, false);
    } catch (InvalidArgumentException) { $secret = true; }
    assert($secret);

    $duplicate = false;
    try {
        new ResolutionDecision(ResolutionCode::InformationProvided, ['Same action', 'Same action'], null,
            'Review the resolution.', true, new DateTimeImmutable('+7 days'), true, false);
    } catch (InvalidArgumentException) { $duplicate = true; }
    assert($duplicate);
});

$test('Luhn filtering detects cards without rejecting ordinary long identifiers', static function (): void {
    assert(SensitiveContentDetector::containsProhibitedSecret('Card 4111 1111 1111 1111'));
    assert(!SensitiveContentDetector::containsProhibitedSecret('Reference 1234567890123456789'));
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-B second-review adversarial tests passed.\n");
