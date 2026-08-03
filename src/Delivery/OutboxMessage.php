<?php

declare(strict_types=1);

namespace Sabri\CF02\Delivery;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class OutboxMessage
{
    private string $status = 'queued';
    private int $attempts = 0;
    private ?DateTimeImmutable $nextAttemptAt = null;
    private ?string $providerReference = null;

    public function __construct(
        private readonly string $messageId,
        private readonly SupportCaseId $caseId,
        private readonly string $channel,
        private readonly string $recipientReference,
        private readonly string $templateKey,
        private readonly string $safePayloadHash,
        private readonly string $idempotencyKey,
        private readonly DateTimeImmutable $queuedAt,
        private readonly bool $alternateChannelConsented
    ) {
        if (preg_match('/^CF02-OUT-[A-F0-9]{20}$/', $messageId) !== 1) {
            throw new InvalidArgumentException('Invalid outbox message ID.');
        }
        if (!in_array($channel, ['email', 'in_app', 'sms', 'chat'], true) || trim($recipientReference) === '') {
            throw new InvalidArgumentException('Invalid delivery channel or recipient.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $templateKey) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $safePayloadHash) !== 1
            || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $idempotencyKey) !== 1) {
            throw new InvalidArgumentException('Invalid outbox template, payload hash or idempotency key.');
        }
        $this->nextAttemptAt = $queuedAt;
    }

    public static function create(
        SupportCaseId $caseId,
        string $channel,
        string $recipientReference,
        string $templateKey,
        string $safePayload,
        string $idempotencyKey,
        DateTimeImmutable $queuedAt,
        bool $alternateChannelConsented
    ): self {
        return new self(
            'CF02-OUT-' . strtoupper(bin2hex(random_bytes(10))),
            $caseId,
            $channel,
            $recipientReference,
            $templateKey,
            hash('sha256', $safePayload),
            $idempotencyKey,
            $queuedAt,
            $alternateChannelConsented
        );
    }

    public function markAttempt(DateTimeImmutable $at): void
    {
        if ($this->status === 'sent' || $this->status === 'dead_letter' || $at < $this->queuedAt) {
            throw new InvalidArgumentException('Outbox message cannot be attempted in its current state.');
        }
        ++$this->attempts;
        $this->status = 'sending';
        $delay = min(3600, 30 * (2 ** min(6, $this->attempts - 1)));
        $this->nextAttemptAt = $at->modify(sprintf('+%d seconds', $delay));
    }

    public function markSent(string $providerReference): void
    {
        if ($this->status !== 'sending' || trim($providerReference) === '') {
            throw new InvalidArgumentException('Outbox message must be sending before success.');
        }
        $this->status = 'sent';
        $this->providerReference = trim($providerReference);
        $this->nextAttemptAt = null;
    }

    public function markFailed(bool $retryable): void
    {
        if ($this->status !== 'sending') {
            throw new InvalidArgumentException('Only a sending message may fail.');
        }
        $this->status = $retryable && $this->attempts < 7 ? 'queued' : 'dead_letter';
        if ($this->status === 'dead_letter') {
            $this->nextAttemptAt = null;
        }
    }

    public function canUseAlternateChannel(): bool { return $this->alternateChannelConsented; }
    public function messageId(): string { return $this->messageId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function channel(): string { return $this->channel; }
    public function recipientReference(): string { return $this->recipientReference; }
    public function templateKey(): string { return $this->templateKey; }
    public function safePayloadHash(): string { return $this->safePayloadHash; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function status(): string { return $this->status; }
    public function attempts(): int { return $this->attempts; }
    public function nextAttemptAt(): ?DateTimeImmutable { return $this->nextAttemptAt; }
    public function providerReference(): ?string { return $this->providerReference; }
}
