<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use DateTimeImmutable;
use InvalidArgumentException;

final class MutationEnvelope
{
    public function __construct(
        private readonly string $idempotencyKey,
        private readonly string $traceId,
        private readonly string $actorReference,
        private readonly string $purpose,
        private readonly int $expectedVersion,
        private readonly DateTimeImmutable $occurredAt,
        private readonly string $payloadFingerprint
    ) {
        if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $idempotencyKey) !== 1) {
            throw new InvalidArgumentException('Invalid mutation idempotency key.');
        }
        if (preg_match('/^tr_[a-f0-9]{32}$/', $traceId) !== 1) {
            throw new InvalidArgumentException('Invalid mutation trace ID.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{2,127}$/', $actorReference) !== 1) {
            throw new InvalidArgumentException('Invalid mutation actor reference.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $purpose) !== 1) {
            throw new InvalidArgumentException('Invalid mutation purpose.');
        }
        if ($expectedVersion < 1) {
            throw new InvalidArgumentException('Expected record version must be positive.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $payloadFingerprint) !== 1) {
            throw new InvalidArgumentException('Invalid mutation payload fingerprint.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function create(
        string $idempotencyKey,
        string $actorReference,
        string $purpose,
        int $expectedVersion,
        DateTimeImmutable $occurredAt,
        array $payload,
        ?string $traceId = null
    ): self {
        $canonical = self::canonicalize($payload);
        $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self(
            $idempotencyKey,
            $traceId ?? 'tr_' . bin2hex(random_bytes(16)),
            $actorReference,
            $purpose,
            $expectedVersion,
            $occurredAt,
            hash('sha256', $encoded)
        );
    }

    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function traceId(): string { return $this->traceId; }
    public function actorReference(): string { return $this->actorReference; }
    public function purpose(): string { return $this->purpose; }
    public function expectedVersion(): int { return $this->expectedVersion; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function payloadFingerprint(): string { return $this->payloadFingerprint; }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private static function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(static fn (mixed $entry): mixed => is_array($entry) ? self::canonicalize($entry) : $entry, $item)
                    : self::canonicalize($item);
            }
        }
        return $value;
    }
}
