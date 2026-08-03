<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;

final class AppealCase
{
    private int $version = 1;
    private AppealState $state = AppealState::Submitted;
    private ?AppealEligibilityDecision $eligibility = null;
    private ?string $reviewerReference = null;
    private ?AppealDecision $decision = null;
    private ?string $nativeCommandId = null;
    private ?string $implementationReference = null;
    private ?DateTimeImmutable $lastMutationAt = null;
    /** @var list<array<string, string|int>> */
    private array $history = [];

    public function __construct(
        private readonly string $appealId,
        private readonly SupportCaseId $caseId,
        private readonly string $appellantReference,
        private readonly AppealDossier $dossier,
        private readonly DateTimeImmutable $submittedAt
    ) {
        if (preg_match('/^CF02-APL-[A-F0-9]{20}$/', $appealId) !== 1 || trim($appellantReference) === '') {
            throw new InvalidArgumentException('Invalid appeal identity.');
        }
        $this->lastMutationAt = $submittedAt;
        $this->history[] = ['type' => 'submitted', 'at' => $submittedAt->format(DATE_ATOM), 'version' => 1];
    }

    public static function submit(
        SupportCaseId $caseId,
        string $appellantReference,
        AppealDossier $dossier,
        DateTimeImmutable $submittedAt
    ): self {
        return new self('CF02-APL-' . strtoupper(bin2hex(random_bytes(10))), $caseId, $appellantReference, $dossier, $submittedAt);
    }

    public function beginEligibility(DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->transition(AppealState::EligibilityReview, [AppealState::Submitted], 'eligibility_review_started', $at, $expectedVersion);
    }

    public function recordEligibility(AppealEligibilityDecision $decision, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::EligibilityReview) {
            throw new DomainException('Appeal is not in eligibility review.');
        }
        $this->eligibility = $decision;
        $this->state = $decision->eligible() ? AppealState::Accepted : AppealState::Rejected;
        $this->record($decision->eligible() ? 'accepted' : 'rejected', $at);
    }

    public function assignReviewer(string $reviewerReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::Accepted || trim($reviewerReference) === '') {
            throw new DomainException('Accepted appeal and reviewer reference are required.');
        }
        $this->reviewerReference = trim($reviewerReference);
        $this->state = AppealState::UnderReview;
        $this->record('reviewer_assigned', $at, ['reviewer' => $this->reviewerReference]);
    }

    public function requestNativeDecision(string $commandId, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::UnderReview || $this->reviewerReference === null || trim($commandId) === '') {
            throw new DomainException('Native decision request requires active independent review.');
        }
        $this->nativeCommandId = trim($commandId);
        $this->state = AppealState::NativeDecisionPending;
        $this->record('native_decision_requested', $at, ['command_id' => $this->nativeCommandId]);
    }

    public function decide(AppealDecision $decision, string $nativeOutcomeReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::NativeDecisionPending || $this->reviewerReference === null || $this->nativeCommandId === null) {
            throw new DomainException('Appeal is not awaiting a native decision.');
        }
        if (!hash_equals($this->reviewerReference, $decision->reviewerReference()) || trim($nativeOutcomeReference) === '') {
            throw new DomainException('Appeal decision reviewer or native outcome reference does not match.');
        }
        $this->decision = $decision;
        $this->implementationReference = trim($nativeOutcomeReference);
        $this->state = AppealState::Decided;
        $this->record('decided', $at, ['outcome' => $decision->outcome(), 'native_outcome' => $this->implementationReference]);
    }

    public function confirmImplemented(string $implementationReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::Decided || $this->decision === null || trim($implementationReference) === '') {
            throw new DomainException('Reasoned decision and implementation reference are required.');
        }
        if ($this->implementationReference !== null && !hash_equals($this->implementationReference, trim($implementationReference))) {
            throw new DomainException('Implementation reference does not match the native decision outcome.');
        }
        $this->state = AppealState::Implemented;
        $this->record('implemented', $at, ['implementation_reference' => trim($implementationReference)]);
    }

    public function close(DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->transition(AppealState::Closed, [AppealState::Rejected, AppealState::Implemented], 'closed', $at, $expectedVersion);
    }

    public function reopen(string $reason, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== AppealState::Closed || trim($reason) === '') {
            throw new DomainException('Closed appeal and reason are required for reopening.');
        }
        $this->state = AppealState::Reopened;
        $this->record('reopened', $at, ['reason' => trim($reason)]);
    }

    public function appealId(): string { return $this->appealId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function state(): AppealState { return $this->state; }
    public function version(): int { return $this->version; }
    public function dossier(): AppealDossier { return $this->dossier; }
    public function decision(): ?AppealDecision { return $this->decision; }
    public function reviewerReference(): ?string { return $this->reviewerReference; }
    public function implementationReference(): ?string { return $this->implementationReference; }
    /** @return list<array<string, string|int>> */ public function history(): array { return $this->history; }

    /** @param list<AppealState> $allowedFrom */
    private function transition(AppealState $target, array $allowedFrom, string $type, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if (!in_array($this->state, $allowedFrom, true)) {
            throw new DomainException(sprintf('Appeal transition from %s to %s is not allowed.', $this->state->value, $target->value));
        }
        $this->state = $target;
        $this->record($type, $at);
    }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale appeal version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }

    private function assertChronology(DateTimeImmutable $at): void
    {
        if ($at < $this->submittedAt || ($this->lastMutationAt !== null && $at < $this->lastMutationAt)) {
            throw new DomainException('Appeal mutation is backdated.');
        }
    }

    /** @param array<string, string|int> $details */
    private function record(string $type, DateTimeImmutable $at, array $details = []): void
    {
        $this->lastMutationAt = $at;
        ++$this->version;
        $this->history[] = ['type' => $type, 'at' => $at->format(DATE_ATOM), 'version' => $this->version] + $details;
    }
}
