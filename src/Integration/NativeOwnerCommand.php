<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Security\MutationEnvelope;

final class NativeOwnerCommand
{
    private int $version = 1;
    private CommandState $state = CommandState::Pending;
    private int $attempts = 0;
    private ?string $providerReference = null;
    private ?string $nativeOutcomeReference = null;
    private ?string $failureCode = null;
    private ?DateTimeImmutable $lastMutationAt = null;
    /** @var list<array<string, string|int>> */
    private array $history = [];

    /** @param array<string, scalar|null> $payload */
    public function __construct(
        private readonly string $commandId,
        private readonly SupportCaseId $caseId,
        private readonly string $nativeOwner,
        private readonly string $action,
        private readonly string $objectReference,
        private readonly array $payload,
        private readonly int $expectedNativeVersion,
        private readonly MutationEnvelope $envelope,
        private readonly DateTimeImmutable $createdAt
    ) {
        if (preg_match('/^CF02-CMD-[A-F0-9]{24}$/', $commandId) !== 1) {
            throw new InvalidArgumentException('Invalid native-owner command ID.');
        }
        if (preg_match('/^(?:file-(?:00|02|09|17|18|21|24)|cf-03)$/', $nativeOwner) !== 1) {
            throw new InvalidArgumentException('Unknown native-owner contract.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $action) !== 1) {
            throw new InvalidArgumentException('Invalid native-owner command action.');
        }
        if (trim($objectReference) === '' || strlen($objectReference) > 190) {
            throw new InvalidArgumentException('Invalid native object reference.');
        }
        if ($expectedNativeVersion < 1) {
            throw new InvalidArgumentException('Expected native version must be positive.');
        }
        if ($payload === [] || count($payload) > 32) {
            throw new InvalidArgumentException('Native-owner command payload must be bounded and non-empty.');
        }
        foreach ($payload as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1) {
                throw new InvalidArgumentException('Invalid native command payload field.');
            }
            if (is_string($value) && strlen($value) > 500) {
                throw new InvalidArgumentException('Native command payload value exceeds the bounded limit.');
            }
        }
        if ($envelope->expectedVersion() !== 1 || $envelope->occurredAt() != $createdAt) {
            throw new InvalidArgumentException('Command envelope is not bound to command creation.');
        }
        $this->lastMutationAt = $createdAt;
        $this->history[] = [
            'type' => 'created',
            'at' => $createdAt->format(DATE_ATOM),
            'version' => 1,
            'trace_id' => $envelope->traceId(),
        ];
    }

    /** @param array<string, scalar|null> $payload */
    public static function create(
        SupportCaseId $caseId,
        string $nativeOwner,
        string $action,
        string $objectReference,
        array $payload,
        int $expectedNativeVersion,
        MutationEnvelope $envelope,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            'CF02-CMD-' . strtoupper(bin2hex(random_bytes(12))),
            $caseId,
            $nativeOwner,
            $action,
            $objectReference,
            $payload,
            $expectedNativeVersion,
            $envelope,
            $createdAt
        );
    }

    public function markDispatched(string $providerReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if (!in_array($this->state, [CommandState::Pending, CommandState::Failed], true)) {
            throw new DomainException('Only pending or retryable failed commands may be dispatched.');
        }
        if (trim($providerReference) === '') {
            throw new InvalidArgumentException('Provider dispatch reference is required.');
        }
        $this->providerReference = trim($providerReference);
        $this->state = CommandState::Dispatched;
        ++$this->attempts;
        $this->record('dispatched', $at, ['provider_reference' => $this->providerReference]);
    }

    public function markSucceeded(
        string $nativeOutcomeReference,
        int $nativeVersion,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== CommandState::Dispatched) {
            throw new DomainException('Only dispatched commands may succeed.');
        }
        if (trim($nativeOutcomeReference) === '' || $nativeVersion < $this->expectedNativeVersion) {
            throw new InvalidArgumentException('Native outcome reference or version is invalid.');
        }
        $this->nativeOutcomeReference = trim($nativeOutcomeReference);
        $this->state = CommandState::Succeeded;
        $this->record('succeeded', $at, [
            'native_outcome_reference' => $this->nativeOutcomeReference,
            'native_version' => $nativeVersion,
        ]);
    }

    public function markFailed(
        string $failureCode,
        bool $retryable,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== CommandState::Dispatched) {
            throw new DomainException('Only dispatched commands may fail.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $failureCode) !== 1) {
            throw new InvalidArgumentException('Invalid native command failure code.');
        }
        $this->failureCode = $failureCode;
        $this->state = $retryable && $this->attempts < 5 ? CommandState::Failed : CommandState::DeadLetter;
        $this->record('failed', $at, [
            'failure_code' => $failureCode,
            'retryable' => $retryable ? 'yes' : 'no',
        ]);
    }

    public function startCompensation(DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== CommandState::Succeeded) {
            throw new DomainException('Compensation requires a succeeded command.');
        }
        $this->state = CommandState::Compensating;
        $this->record('compensation_started', $at);
    }

    public function markCompensated(string $outcomeReference, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertChronology($at);
        if ($this->state !== CommandState::Compensating || trim($outcomeReference) === '') {
            throw new DomainException('Compensation completion requires an active compensation and outcome reference.');
        }
        $this->nativeOutcomeReference = trim($outcomeReference);
        $this->state = CommandState::Compensated;
        $this->record('compensated', $at, ['outcome_reference' => $this->nativeOutcomeReference]);
    }

    public function commandId(): string { return $this->commandId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function nativeOwner(): string { return $this->nativeOwner; }
    public function action(): string { return $this->action; }
    public function objectReference(): string { return $this->objectReference; }
    /** @return array<string, scalar|null> */ public function payload(): array { return $this->payload; }
    public function expectedNativeVersion(): int { return $this->expectedNativeVersion; }
    public function envelope(): MutationEnvelope { return $this->envelope; }
    public function state(): CommandState { return $this->state; }
    public function attempts(): int { return $this->attempts; }
    public function version(): int { return $this->version; }
    public function providerReference(): ?string { return $this->providerReference; }
    public function nativeOutcomeReference(): ?string { return $this->nativeOutcomeReference; }
    public function failureCode(): ?string { return $this->failureCode; }
    /** @return list<array<string, string|int>> */ public function history(): array { return $this->history; }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale command version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }

    private function assertChronology(DateTimeImmutable $at): void
    {
        if ($at < $this->createdAt || ($this->lastMutationAt !== null && $at < $this->lastMutationAt)) {
            throw new DomainException('Native command mutation is backdated.');
        }
    }

    /** @param array<string, string|int> $details */
    private function record(string $type, DateTimeImmutable $at, array $details = []): void
    {
        $this->lastMutationAt = $at;
        ++$this->version;
        $this->history[] = ['type' => $type, 'at' => $at->format(DATE_ATOM), 'version' => $this->version] + $details;
    }
}
