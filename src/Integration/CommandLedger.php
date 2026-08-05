<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use DateTimeImmutable;
use DomainException;
use Sabri\CF02\Security\ReplayGuard;

final class CommandLedger
{
    /** @var array<string, NativeOwnerCommand> */
    private array $commands = [];
    /** @var array<string, string> */
    private array $idempotencyToCommand = [];

    public function __construct(private readonly ReplayGuard $replayGuard)
    {
    }

    public function register(NativeOwnerCommand $command, DateTimeImmutable $now): NativeOwnerCommand
    {
        if (!$command->envelope()->matchesPayload($command->payload())) {
            throw new DomainException('Native command payload is not bound to its mutation envelope.');
        }
        $key = $command->envelope()->idempotencyKey();
        $existingId = $this->idempotencyToCommand[$key] ?? null;
        if ($existingId !== null) {
            $existing = $this->commands[$existingId];
            if (!hash_equals($existing->envelope()->payloadFingerprint(), $command->envelope()->payloadFingerprint())
                || !hash_equals($existing->nativeOwner(), $command->nativeOwner())
                || !hash_equals($existing->action(), $command->action())
                || !hash_equals($existing->objectReference(), $command->objectReference())) {
                throw new DomainException('Native command idempotency collision detected.');
            }
            return $existing;
        }
        $this->replayGuard->accept($command->envelope(), $now);
        if (isset($this->commands[$command->commandId()])) {
            throw new DomainException('Native command ID collision detected.');
        }
        $this->commands[$command->commandId()] = $command;
        $this->idempotencyToCommand[$key] = $command->commandId();
        return $command;
    }

    public function get(string $commandId): NativeOwnerCommand
    {
        if (!isset($this->commands[$commandId])) {
            throw new DomainException('Native command was not found.');
        }
        return $this->commands[$commandId];
    }

    /** @return list<NativeOwnerCommand> */
    public function retryable(): array
    {
        return array_values(array_filter(
            $this->commands,
            static fn (NativeOwnerCommand $command): bool => $command->state() === CommandState::Failed && $command->attempts() < 5
        ));
    }

    /** @return list<NativeOwnerCommand> */
    public function unresolved(): array
    {
        $terminal = [CommandState::Succeeded, CommandState::Compensated];
        return array_values(array_filter(
            $this->commands,
            static fn (NativeOwnerCommand $command): bool => !in_array($command->state(), $terminal, true)
        ));
    }

    public function count(): int { return count($this->commands); }
}
