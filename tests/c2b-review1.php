<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Attachment\AttachmentRecord;
use Sabri\CF02\Attachment\AttachmentState;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseWorkspace;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Intake\IdempotencyKey;
use Sabri\CF02\Intake\IntakeChannel;
use Sabri\CF02\Portal\UserCaseProjection;
use Sabri\CF02\Receipt\ReceiptFactory;
use Sabri\CF02\Resolution\ResolutionCode;
use Sabri\CF02\Resolution\ResolutionDecision;
use Sabri\CF02\Thread\CaseMessage;
use Sabri\CF02\Thread\MessageVisibility;
use Sabri\CF02\Intake\IntakeRequest;
use Sabri\CF02\Intake\TriagePolicy;

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

$test('idempotency preserves exact requester identity', static function (): void {
    $upper = IdempotencyKey::forIntake(IntakeChannel::Web, 'User-A', 'message-1');
    $lower = IdempotencyKey::forIntake(IntakeChannel::Web, 'user-a', 'message-1');
    assert($upper->value() !== $lower->value());
});

$test('message idempotency collision with changed payload is rejected', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $first = new CaseMessage('m1', 'user-1', MessageVisibility::Requester, 'The first bounded message body is long enough.', 'portal', 'same-key', new DateTimeImmutable());
    $changed = new CaseMessage('m2', 'user-1', MessageVisibility::Requester, 'A different bounded message body is long enough.', 'portal', 'same-key', new DateTimeImmutable());
    assert($case->appendMessage($first, 1));

    $thrown = false;
    try {
        $case->appendMessage($changed, 2);
    } catch (DomainException) {
        $thrown = true;
    }
    assert($thrown);
});

$test('attachment identifier collision is rejected but exact replay is idempotent', static function (): void {
    $caseId = SupportCaseId::generate();
    $case = new CaseWorkspace($caseId, 'user-1', 'technical');
    $first = new AttachmentRecord('att-1', $caseId, hash('sha256', 'a'), 'text/plain', 10, 'technical evidence', new DateTimeImmutable(), 'private://a', 'C2');
    $replay = new AttachmentRecord('att-1', $caseId, hash('sha256', 'a'), 'text/plain', 10, 'technical evidence', new DateTimeImmutable(), 'private://other-storage-mapping', 'C2');
    $changed = new AttachmentRecord('att-1', $caseId, hash('sha256', 'b'), 'text/plain', 10, 'technical evidence', new DateTimeImmutable(), 'private://b', 'C2');

    assert($case->addAttachment($first, 1));
    assert(!$case->addAttachment($replay, 2));

    $thrown = false;
    try {
        $case->addAttachment($changed, 2);
    } catch (DomainException) {
        $thrown = true;
    }
    assert($thrown);
});

$test('requester projection hides quarantined and requester-hidden attachments', static function (): void {
    $caseId = SupportCaseId::generate();
    $case = new CaseWorkspace($caseId, 'user-1', 'technical');

    $quarantined = new AttachmentRecord('att-q', $caseId, hash('sha256', 'q'), 'text/plain', 10, 'diagnostic', new DateTimeImmutable(), 'private://q', 'C2');
    $hidden = new AttachmentRecord('att-h', $caseId, hash('sha256', 'h'), 'text/plain', 10, 'internal diagnostic', new DateTimeImmutable(), 'private://h', 'C2', false, false);
    $hidden->transition(AttachmentState::Quarantined);
    $hidden->transition(AttachmentState::Scanned);
    $hidden->transition(AttachmentState::Available);

    assert($case->addAttachment($quarantined, 1));
    assert($case->addAttachment($hidden, 2));
    $projection = UserCaseProjection::fromWorkspace($case);
    assert($projection['attachment_ids'] === []);
});

$test('direct resolved and closed state bypass is rejected', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $case->transition(CaseState::Triaged, 1);
    $case->transition(CaseState::InProgress, 2);

    $thrown = false;
    try {
        $case->transition(CaseState::Resolved, 3);
    } catch (DomainException) {
        $thrown = true;
    }
    assert($thrown);
});

$test('governed resolution and closure require verified outcome and notice', static function (): void {
    $case = new CaseWorkspace(SupportCaseId::generate(), 'user-1', 'technical');
    $case->transition(CaseState::Triaged, 1);
    $case->transition(CaseState::InProgress, 2);

    $decision = new ResolutionDecision(
        ResolutionCode::InformationProvided,
        ['Provided bounded troubleshooting steps'],
        null,
        'Please verify the corrected behavior.',
        true,
        new DateTimeImmutable('+7 days'),
        true,
        true
    );

    $case->resolve($decision, [], 3, new DateTimeImmutable());
    assert($case->state() === CaseState::Resolved);
    $case->close(false, 4);
    assert($case->state() === CaseState::Closed);
    assert(UserCaseProjection::fromWorkspace($case, new DateTimeImmutable())->offsetGet('can_reopen') ?? true);
});

$test('receipt shows unverified sender trust without granting authority', static function (): void {
    $request = new IntakeRequest(
        'user-1',
        IntakeChannel::Email,
        'mail-2',
        false,
        'technical',
        'The page fails consistently after the latest navigation attempt.',
        ['route' => '/support', 'device_context' => 'Chrome', 'reproduction_steps' => 'Open and submit.'],
        'medium',
        'normal',
        'en-US'
    );
    $triage = (new TriagePolicy())->decide($request);
    $receipt = (new ReceiptFactory())->create(SupportCaseId::generate(), $request, $triage, true);
    assert($receipt->senderTrust() === 'unverified');
    assert($triage->humanReviewRequired());
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 C2-B first-review regressions passed.\n");
