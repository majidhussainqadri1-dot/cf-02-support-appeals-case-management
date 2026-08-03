<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Attachment\AttachmentRecord;
use Sabri\CF02\Resolution\ResolutionDecision;
use Sabri\CF02\Resolution\ResolutionPolicy;
use Sabri\CF02\Thread\CaseMessage;
use Sabri\CF02\Thread\CaseThread;

final class CaseWorkspace
{
    private CaseState $state = CaseState::New;
    private int $version = 1;
    private CaseThread $thread;
    private ?ResolutionDecision $resolution = null;
    /** @var array<string, AttachmentRecord> */ private array $attachments = [];
    /** @var array<string, string> */ private array $linkedObjects = [];
    /** @var array<string, string> */ private array $tasks = [];
    /** @var array<string, string> */ private array $blockers = [];

    public function __construct(
        private readonly SupportCaseId $caseId,
        private readonly string $requesterReference,
        private readonly string $categoryKey,
        private ?string $ownerReference = null,
        private string $slaSummary = 'Not yet assigned'
    ) {
        if (trim($requesterReference) === '' || trim($categoryKey) === '') {
            throw new InvalidArgumentException('Case requester and category are required.');
        }
        $this->thread = new CaseThread();
    }

    public function appendMessage(CaseMessage $message, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        $added = $this->thread->append($message);
        if ($added) {
            ++$this->version;
        }
        return $added;
    }

    public function addAttachment(AttachmentRecord $attachment, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        if (!$attachment->caseId()->equals($this->caseId)) {
            throw new InvalidArgumentException('Attachment belongs to another case.');
        }

        $existing = $this->attachments[$attachment->attachmentId()] ?? null;
        if ($existing instanceof AttachmentRecord) {
            if (!hash_equals($existing->identityFingerprint(), $attachment->identityFingerprint())) {
                throw new DomainException('Attachment identifier was reused with different content or metadata.');
            }
            return false;
        }

        $this->attachments[$attachment->attachmentId()] = $attachment;
        ++$this->version;
        return true;
    }

    public function addBlocker(string $id, string $description, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        if (trim($id) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Blocker identity and description are required.');
        }

        if (isset($this->blockers[$id])) {
            if (!hash_equals($this->blockers[$id], $description)) {
                throw new DomainException('Blocker identifier was reused with a different description.');
            }
            return false;
        }

        $this->blockers[$id] = $description;
        ++$this->version;
        return true;
    }

    public function clearBlocker(string $id, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        if (!isset($this->blockers[$id])) {
            return false;
        }
        unset($this->blockers[$id]);
        ++$this->version;
        return true;
    }

    public function transition(CaseState $to, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (in_array($to, [CaseState::Resolved, CaseState::Closed], true)) {
            throw new DomainException('Resolved and Closed states require the governed resolve() or close() command.');
        }
        (new CaseStateMachine())->assertTransition($this->state, $to);
        $this->state = $to;
        ++$this->version;
    }

    /** @param list<string> $failedNativeCommands */
    public function resolve(
        ResolutionDecision $decision,
        array $failedNativeCommands,
        int $expectedVersion,
        ?DateTimeImmutable $now = null
    ): void {
        $this->assertVersion($expectedVersion);
        $reasons = (new ResolutionPolicy())->validate(
            $this->state,
            $decision,
            array_values($this->blockers),
            $failedNativeCommands,
            $now
        );

        if ($reasons !== []) {
            throw new DomainException(implode(' ', $reasons));
        }

        (new CaseStateMachine())->assertTransition($this->state, CaseState::Resolved);
        $this->resolution = $decision;
        $this->state = CaseState::Resolved;
        ++$this->version;
    }

    public function close(bool $userConfirmed, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if ($this->state !== CaseState::Resolved || !$this->resolution instanceof ResolutionDecision) {
            throw new DomainException('Only a governed resolved case may be closed.');
        }

        if (!$userConfirmed && !($this->resolution->autoCloseEligible() && $this->resolution->closureNoticeSent())) {
            throw new DomainException('Closure requires user confirmation or a noticed eligible auto-close policy.');
        }

        (new CaseStateMachine())->assertTransition($this->state, CaseState::Closed);
        $this->state = CaseState::Closed;
        ++$this->version;
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function requesterReference(): string { return $this->requesterReference; }
    public function categoryKey(): string { return $this->categoryKey; }
    public function state(): CaseState { return $this->state; }
    public function version(): int { return $this->version; }
    public function thread(): CaseThread { return $this->thread; }
    /** @return array<string, AttachmentRecord> */ public function attachments(): array { return $this->attachments; }
    /** @return array<string, string> */ public function linkedObjects(): array { return $this->linkedObjects; }
    /** @return array<string, string> */ public function tasks(): array { return $this->tasks; }
    /** @return array<string, string> */ public function blockers(): array { return $this->blockers; }
    public function ownerReference(): ?string { return $this->ownerReference; }
    public function slaSummary(): string { return $this->slaSummary; }
    public function resolution(): ?ResolutionDecision { return $this->resolution; }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale case version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }
}
