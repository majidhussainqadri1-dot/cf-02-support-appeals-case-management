<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

use InvalidArgumentException;
use Sabri\CF02\Attachment\AttachmentRecord;
use Sabri\CF02\Thread\CaseMessage;
use Sabri\CF02\Thread\CaseThread;

final class CaseWorkspace
{
    private CaseState $state = CaseState::New;
    private int $version = 1;
    private CaseThread $thread;
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

    public function addAttachment(AttachmentRecord $attachment, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (!$attachment->caseId()->equals($this->caseId)) {
            throw new InvalidArgumentException('Attachment belongs to another case.');
        }
        $this->attachments[$attachment->attachmentId()] = $attachment;
        ++$this->version;
    }

    public function addBlocker(string $id, string $description, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (trim($id) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Blocker identity and description are required.');
        }
        $this->blockers[$id] = $description;
        ++$this->version;
    }

    public function clearBlocker(string $id, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        unset($this->blockers[$id]);
        ++$this->version;
    }

    public function transition(CaseState $to, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        (new CaseStateMachine())->assertTransition($this->state, $to);
        $this->state = $to;
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

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale case version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }
}
