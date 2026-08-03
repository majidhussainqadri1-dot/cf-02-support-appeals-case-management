<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use InvalidArgumentException;

final class IdempotencyKey
{
    private function __construct(private readonly string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid idempotency key.');
        }
    }

    public static function forIntake(IntakeChannel $channel, string $requesterReference, string $sourceMessageId): self
    {
        $requesterReference = trim($requesterReference);
        $sourceMessageId = trim($sourceMessageId);

        if ($requesterReference === '' || $sourceMessageId === '') {
            throw new InvalidArgumentException('Requester and source message identifiers are required for idempotency.');
        }

        return new self(hash('sha256', implode('|', [
            'cf02-intake-v1',
            $channel->value,
            mb_strtolower($requesterReference),
            $sourceMessageId,
        ])));
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
