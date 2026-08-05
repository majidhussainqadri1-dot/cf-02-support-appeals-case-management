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
use Sabri\CF02\Deduplication\CaseMergePlan;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseWorkspace;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Intake\IntakeChannel;
use Sabri\CF02\Intake\IntakeRequest;
use Sabri\CF02\Intake\TriagePolicy;
use Sabri\CF02\Portal\UserCaseProjection;
use Sabri\CF02\Receipt\ReceiptFactory;
use Sabri\CF02\Resolution\ResolutionCode;
use Sabri\CF02\Resolution\ResolutionDecision;
use Sabri\CF02\Resolution\ResolutionPolicy;
use Sabri\CF02\Thread\CaseMessage;
use Sabri\CF02\Thread\MessageVisibility;

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

$technicalRequest = static fn (string $source = 'web-001', bool $verified = true): IntakeRequest => new IntakeRequest(
    'user-100', IntakeChannel::Web, $source, $verified, 'technical',
    'The support page shows a repeatable error after opening the case form.',
    ['route' => '/support/new', 'device_context' => 'Chrome 151 on Windows 10', 'reproduction_steps' => 'Open support, choose Technical Fault, submit the form.'],
    'medium', 'normal', 'ur-PK', ['keyboard'], true
);

$test('case identifiers are non-sequential UUID identities', static function (): void {
    $first = SupportCaseId::generate();
    $second = SupportCaseId::generate();
    assert(!$first->equals($second));
    assert(SupportCaseId::fromString($first->value())->equals($first));
});

$test('guided intake validates dynamic fields and stable idempotency', static function () use ($technicalRequest): void {
    $first = $technicalRequest('web-001');
    $replay = $technicalRequest('web-001');
    $other = $technicalRequest('web-002');
    assert($first->idempotencyKey()->value() === $replay->idempotencyKey()->value());
    assert($first->fingerprint() === $replay->fingerprint());
    assert($first->idempotencyKey()->value() !== $other->idempotencyKey()->value());
    assert($first->diagnosticsConsent());
});

$test('guided intake rejects passwords OTPs and payment-card data', static function (): void {
    $thrown = false;
    try {
        new IntakeRequest('user-100', IntakeChannel::Web, 'secret-001', true, 'technical',
            'My password: secret123 and the page does not open correctly.',
            ['route' => '/support/new', 'device_context' => 'Browser', 'reproduction_steps' => 'Submit the form.'],
            'medium', 'normal', 'en-US');
    } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('triage exposes sender trust and requires human review', static function () use ($technicalRequest): void {
    $decision = (new TriagePolicy())->decide($technicalRequest('email-001', false));
    assert($decision->queueKey() === 'technical');
    assert($decision->humanReviewRequired());
    assert(in_array('Sender trust is unverified; no identity-sensitive action may be taken from the intake alone.', $decision->reasons(), true));
});

$test('privacy intake remains specialist-only and purpose-bound', static function (): void {
    $request = new IntakeRequest('user-200', IntakeChannel::Web, 'privacy-001', true, 'privacy_data_rights',
        'I request a scoped copy of the personal data connected with my account.',
        ['request_type' => 'access', 'identity_verification_reference' => 'File00-verification-ref-1', 'scope' => 'account and profile data'],
        'high', 'urgent', 'en-US');
    $decision = (new TriagePolicy())->decide($request);
    assert($decision->queueKey() === 'privacy_liaison');
    assert($decision->specialistRequired());
    assert($decision->humanReviewRequired());
});

$test('case thread is idempotent and never exposes internal notes to requester', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-100', 'technical');
    $public = new CaseMessage('msg-1', 'user-100', MessageVisibility::Requester, 'The error remains reproducible after retry.', 'portal', 'idem-public-1', new DateTimeImmutable('2026-08-03T19:00:00+05:00'));
    $internal = new CaseMessage('note-1', 'agent-1', MessageVisibility::Internal, 'Check the bounded diagnostic reference before replying.', 'internal', 'idem-note-1', new DateTimeImmutable('2026-08-03T19:01:00+05:00'));
    assert($case->appendMessage($public, 1));
    assert(!$case->appendMessage($public, 2));
    assert($case->appendMessage($internal, 2));
    $projection = UserCaseProjection::fromWorkspace($case);
    assert(count($projection['messages']) === 1);
    assert($projection['messages'][0]['message_id'] === 'msg-1');
    assert($projection['messages'][0]['author_label'] === 'Requester');
    assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'bounded diagnostic'));
});

$test('internal note cannot accidentally use requester delivery channel', static function (): void {
    $thrown = false;
    try {
        new CaseMessage('note-x', 'agent-1', MessageVisibility::Internal, 'Internal-only investigation note.', 'email', 'idem-note-x', new DateTimeImmutable());
    } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('case workspace rejects stale writes and foreign-case attachments', static function (): void {
    $caseId = SupportCaseId::generate();
    $case = new CaseWorkspace($caseId, 'user-100', 'technical');
    $case->addBlocker('block-1', 'Awaiting bounded diagnostic evidence.', 1);
    $stale = false;
    try { $case->clearBlocker('block-1', 1); } catch (ConcurrencyConflict) { $stale = true; }
    assert($stale);
    $foreign = new AttachmentRecord('att-foreign', SupportCaseId::generate(), hash('sha256', 'foreign'), 'text/plain', 20, 'technical evidence', new DateTimeImmutable(), 'private://attachments/foreign', 'C2');
    $rejected = false;
    try { $case->addAttachment($foreign, 2); } catch (InvalidArgumentException) { $rejected = true; }
    assert($rejected);
});

$test('attachment remains inaccessible before verified scan and across case boundary', static function (): void {
    $caseId = SupportCaseId::generate();
    $hash = hash('sha256', 'safe-file');
    $attachment = new AttachmentRecord('att-1', $caseId, $hash, 'image/png', 1024, 'screen capture for technical diagnosis', new DateTimeImmutable('2026-08-03T19:00:00+05:00'), 'private://attachments/att-1', 'C2');
    assert($attachment->downloadReference($caseId, 'requester') === null);
    $attachment->transition(AttachmentState::Quarantined);
    $attachment->recordScan(new AttachmentScanResult($hash, 'image/png', 'clean', 'scanner', '1.0.0', new DateTimeImmutable()));
    $attachment->transition(AttachmentState::Redacted, 'private://redacted/att-1');
    assert($attachment->downloadReference($caseId, 'requester') === 'private://redacted/att-1');
    assert($attachment->downloadReference(SupportCaseId::generate(), 'requester') === null);
});

$test('C4 evidence requires specialized vault reference', static function (): void {
    $thrown = false;
    try {
        new AttachmentRecord('att-c4', SupportCaseId::generate(), hash('sha256', 'identity evidence'), 'application/pdf', 2048, 'identity verification evidence', new DateTimeImmutable(), 'private://ordinary/att-c4', 'C4', false);
    } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown);
});

$test('deduplication forbids cross-requester merge and supports audited reversal', static function (): void {
    $crossRequester = false;
    try { new CaseMergePlan(SupportCaseId::generate(), SupportCaseId::generate(), 'user-1', 'user-2', 'Probable replay', hash('sha256', 'preview')); }
    catch (InvalidArgumentException) { $crossRequester = true; }
    assert($crossRequester);
    $plan = new CaseMergePlan(SupportCaseId::generate(), SupportCaseId::generate(), 'user-1', 'user-1', 'Duplicate web replay', hash('sha256', 'preview'));
    $mismatch = false;
    try { $plan->apply(hash('sha256', 'changed-preview')); } catch (DomainException) { $mismatch = true; }
    assert($mismatch);
    $plan->apply(hash('sha256', 'preview'));
    $plan->reverse('Incorrect duplicate match');
    assert($plan->status() === 'reversed');
    assert($plan->reversalReason() === 'Incorrect duplicate match');
});

$test('receipt never claims provider delivery when provider is unavailable', static function (): void {
    $request = new IntakeRequest('user-100', IntakeChannel::Email, 'mail-001', true, 'technical',
        'The support page shows a repeatable error after opening the case form.',
        ['route' => '/support/new', 'device_context' => 'Email client and Chrome', 'reproduction_steps' => 'Open support and submit.'],
        'medium', 'normal', 'en-US');
    $triage = (new TriagePolicy())->decide($request);
    $receipt = (new ReceiptFactory())->create(SupportCaseId::generate(), $request, $triage, false, new DateTimeImmutable());
    assert($receipt->deliveryStatus() === 'queued');
    assert($receipt->status() === 'new');
});

$test('resolution law blocks open blockers failed native commands and silent auto-close', static function (): void {
    $decision = new ResolutionDecision(ResolutionCode::NativeOwnerActionCompleted,
        ['Requested native owner action', 'Verified resulting state'], 'File21:decision:100:v2',
        'Please confirm that the corrected publication state is now visible.', true,
        new DateTimeImmutable('+7 days'), false, true);
    $reasons = (new ResolutionPolicy())->validate(CaseState::InProgress, $decision, ['block-1'], ['command-1'], new DateTimeImmutable());
    assert(in_array('Case has open blockers.', $reasons, true));
    assert(in_array('A required native-owner command failed or remains unreconciled.', $reasons, true));
    assert(in_array('Automatic closure requires prior user notice.', $reasons, true));
});

if ($failures !== []) { exit(1); }
fwrite(STDOUT, "All CF-02 C2-B foundation tests passed.\n");
