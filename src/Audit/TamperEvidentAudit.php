<?php

declare(strict_types=1);

namespace Sabri\CF02\Audit;

use DateTimeImmutable;
use InvalidArgumentException;

final class TamperEvidentAudit
{
    /**
     * @param array<string, scalar|null> $context
     * @return array<string, string|int>
     */
    public function createEvent(
        string $objectType,
        string $objectReference,
        string $actorReference,
        string $purpose,
        string $action,
        string $resultCode,
        string $traceId,
        int $objectVersion,
        array $context,
        DateTimeImmutable $occurredAt,
        ?string $previousHash
    ): array {
        foreach ([$objectType, $objectReference, $actorReference, $purpose, $action, $resultCode, $traceId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Audit event fields are required.');
            }
        }
        if ($objectVersion < 1 || ($previousHash !== null && preg_match('/^[a-f0-9]{64}$/', $previousHash) !== 1)) {
            throw new InvalidArgumentException('Invalid audit version or previous hash.');
        }
        $safeContext = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || preg_match('/(?:password|otp|secret|token|card|body|clinical)/i', $key) === 1) {
                throw new InvalidArgumentException('Audit context contains a prohibited field.');
            }
            $safeContext[$key] = $value;
        }
        ksort($safeContext);
        $contextHash = hash('sha256', json_encode($safeContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $payload = [
            'event_uuid' => 'CF02-AUD-' . strtoupper(bin2hex(random_bytes(10))),
            'object_type' => $objectType,
            'object_ref' => $objectReference,
            'actor_ref' => $actorReference,
            'purpose' => $purpose,
            'action' => $action,
            'result_code' => $resultCode,
            'trace_id' => $traceId,
            'object_version' => $objectVersion,
            'context_hash' => $contextHash,
            'previous_hash' => $previousHash ?? '',
            'occurred_at' => $occurredAt->format(DATE_ATOM),
        ];
        $eventHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $payload + ['event_hash' => $eventHash];
    }

    /** @param list<array<string, string|int>> $events */
    public function verify(array $events): bool
    {
        $previous = null;
        foreach ($events as $event) {
            $expectedPrevious = $previous ?? '';
            if (($event['previous_hash'] ?? null) !== $expectedPrevious) {
                return false;
            }
            $copy = $event;
            $actualHash = (string) ($copy['event_hash'] ?? '');
            unset($copy['event_hash']);
            $expectedHash = hash('sha256', json_encode($copy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            if (!hash_equals($expectedHash, $actualHash)) {
                return false;
            }
            $previous = $actualHash;
        }
        return true;
    }
}
