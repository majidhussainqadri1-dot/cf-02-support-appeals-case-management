<?php

declare(strict_types=1);

namespace Sabri\CF02\Thread;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class CaseMessage
{
    /** @param list<string> $attachmentIds */
    public function __construct(
        private readonly string $messageId,
        private readonly string $authorReference,
        private readonly MessageVisibility $visibility,
        private readonly string $body,
        private readonly string $channel,
        private readonly string $idempotencyKey,
        private readonly DateTimeImmutable $createdAt,
        private readonly array $attachmentIds = [],
        private readonly string $translationState = 'original'
    ) {
        foreach ([$messageId, $authorReference, $body, $idempotencyKey] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Message identity, author, body and idempotency key are required.');
            }
        }

        if (!in_array($channel, ['portal', 'email', 'system', 'internal'], true)) {
            throw new InvalidArgumentException('Invalid message channel.');
        }

        if ($visibility !== MessageVisibility::Requester && $channel !== 'internal') {
            throw new InvalidArgumentException('Internal or restricted notes cannot use a requester delivery channel.');
        }

        if ($visibility === MessageVisibility::Requester && $channel === 'internal') {
            throw new InvalidArgumentException('Requester messages cannot be placed on the internal-only channel.');
        }

        if (!in_array($translationState, ['original', 'machine_draft', 'human_reviewed'], true)) {
            throw new InvalidArgumentException('Invalid translation state.');
        }

        foreach ($attachmentIds as $attachmentId) {
            if (!is_string($attachmentId) || trim($attachmentId) === '') {
                throw new InvalidArgumentException('Invalid attachment identifier.');
            }
        }

        if (count($attachmentIds) !== count(array_unique($attachmentIds))) {
            throw new InvalidArgumentException('Duplicate attachment identifiers are not allowed.');
        }

        if (SensitiveContentDetector::containsProhibitedSecret($body)) {
            throw new InvalidArgumentException('Prohibited secret detected in case message.');
        }
    }

    public function messageId(): string { return $this->messageId; }
    public function authorReference(): string { return $this->authorReference; }
    public function visibility(): MessageVisibility { return $this->visibility; }
    public function body(): string { return $this->body; }
    public function channel(): string { return $this->channel; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    /** @return list<string> */ public function attachmentIds(): array { return $this->attachmentIds; }
    public function translationState(): string { return $this->translationState; }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'message_id' => $this->messageId,
            'author' => $this->authorReference,
            'visibility' => $this->visibility->value,
            'body' => $this->body,
            'channel' => $this->channel,
            'attachments' => $this->attachmentIds,
            'translation_state' => $this->translationState,
        ], JSON_THROW_ON_ERROR));
    }
}
