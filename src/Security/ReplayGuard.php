<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class ReplayGuard
{
    /** @var array<string, array{fingerprint:string, recorded_at:DateTimeImmutable}> */
    private array $records = [];

    public function __construct(private readonly int $replayWindowSeconds = 900)
    {
        if ($replayWindowSeconds < 30 || $replayWindowSeconds > 86400) {
            throw new InvalidArgumentException('Replay window must be between 30 seconds and one day.');
        }
    }

    public function accept(MutationEnvelope $envelope, DateTimeImmutable $now): bool
    {
        $delta = abs($now->getTimestamp() - $envelope->occurredAt()->getTimestamp());
        if ($delta > $this->replayWindowSeconds) {
            throw new DomainException('Mutation timestamp is outside the permitted replay window.');
        }

        $key = $envelope->idempotencyKey();
        $existing = $this->records[$key] ?? null;
        if ($existing !== null) {
            if (!hash_equals($existing['fingerprint'], $envelope->payloadFingerprint())) {
                throw new DomainException('Idempotency key was reused with a different payload.');
            }
            return false;
        }

        $this->records[$key] = [
            'fingerprint' => $envelope->payloadFingerprint(),
            'recorded_at' => $now,
        ];
        $this->purgeExpired($now);
        return true;
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        $removed = 0;
        foreach ($this->records as $key => $record) {
            if ($record['recorded_at']->getTimestamp() + $this->replayWindowSeconds < $now->getTimestamp()) {
                unset($this->records[$key]);
                ++$removed;
            }
        }
        return $removed;
    }

    public function count(): int
    {
        return count($this->records);
    }
}
