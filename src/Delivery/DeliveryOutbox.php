<?php

declare(strict_types=1);

namespace Sabri\CF02\Delivery;

use DateTimeImmutable;
use DomainException;

final class DeliveryOutbox
{
    /** @var array<string, OutboxMessage> */
    private array $messages = [];
    /** @var array<string, string> */
    private array $idempotency = [];

    public function enqueue(OutboxMessage $message): OutboxMessage
    {
        $key = $message->idempotencyKey();
        $existingId = $this->idempotency[$key] ?? null;
        if ($existingId !== null) {
            $existing = $this->messages[$existingId];
            if ($existing->caseId()->value() !== $message->caseId()->value()
                || $existing->channel() !== $message->channel()
                || $existing->safePayloadHash() !== $message->safePayloadHash()) {
                throw new DomainException('Outbox idempotency key was reused for different delivery content.');
            }
            return $existing;
        }
        $this->messages[$message->messageId()] = $message;
        $this->idempotency[$key] = $message->messageId();
        return $message;
    }

    /** @return list<OutboxMessage> */
    public function ready(DateTimeImmutable $at, int $limit = 100): array
    {
        $ready = array_values(array_filter($this->messages, static function (OutboxMessage $message) use ($at): bool {
            return $message->status() === 'queued'
                && $message->nextAttemptAt() !== null
                && $message->nextAttemptAt() <= $at;
        }));
        usort($ready, static fn (OutboxMessage $a, OutboxMessage $b): int => strcmp($a->messageId(), $b->messageId()));
        return array_slice($ready, 0, max(1, min(100, $limit)));
    }

    /** @return list<OutboxMessage> */
    public function deadLetters(): array
    {
        return array_values(array_filter($this->messages, static fn (OutboxMessage $message): bool => $message->status() === 'dead_letter'));
    }

    public function count(): int { return count($this->messages); }
}
