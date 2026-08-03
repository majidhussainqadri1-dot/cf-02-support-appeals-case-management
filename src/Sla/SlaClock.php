<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;

final class SlaClock
{
    private int $version = 1;
    private readonly DateTimeImmutable $firstResponseDeadline;
    private DateTimeImmutable $nextUpdateDeadline;
    private DateTimeImmutable $resolutionDeadline;
    private ?DateTimeImmutable $firstResponseAt = null;
    private ?DateTimeImmutable $lastUpdateAt = null;
    private ?DateTimeImmutable $resolvedAt = null;
    private ?SlaPauseReason $pauseReason = null;
    private ?DateTimeImmutable $pauseStartedAt = null;
    private ?string $pauseEvidenceReference = null;
    private DateTimeImmutable $lastMutationAt;
    /** @var list<array<string, string|int>> */
    private array $pauseHistory = [];

    public function __construct(
        private readonly SlaPolicy $policy,
        private readonly CoverageCalendar $calendar,
        private readonly DateTimeImmutable $startedAt
    ) {
        if ($policy->calendarReference() !== $calendar->reference()) {
            throw new InvalidArgumentException('SLA policy and coverage calendar references do not match.');
        }
        $this->firstResponseDeadline = $calendar->addWorkingMinutes($startedAt, $policy->firstResponseMinutes());
        $this->nextUpdateDeadline = $calendar->addWorkingMinutes($startedAt, $policy->updateMinutes());
        $this->resolutionDeadline = $calendar->addWorkingMinutes($startedAt, $policy->resolutionMinutes());
        $this->lastMutationAt = $startedAt;
    }

    public function recordFirstResponse(DateTimeImmutable $at, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        if ($this->resolvedAt !== null) {
            throw new DomainException('Resolved SLA clock cannot receive a first response.');
        }
        if ($this->firstResponseAt !== null) {
            if ($this->firstResponseAt == $at) {
                return false;
            }
            throw new DomainException('First-response timestamp is immutable once recorded.');
        }

        $this->firstResponseAt = $at;
        $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($at, $this->policy->updateMinutes());
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function recordUpdate(DateTimeImmutable $at, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        if ($this->firstResponseAt === null || $this->resolvedAt !== null) {
            throw new DomainException('Update requires an active clock with a recorded first response.');
        }
        if ($this->lastUpdateAt !== null && $this->lastUpdateAt == $at) {
            return false;
        }

        $this->lastUpdateAt = $at;
        $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($at, $this->policy->updateMinutes());
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function pause(
        SlaPauseReason $reason,
        string $evidenceReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        if ($this->resolvedAt !== null || $this->pauseReason !== null) {
            throw new DomainException('SLA clock is resolved or already paused.');
        }
        if (!$this->policy->allowsPause($reason)) {
            throw new DomainException('SLA policy does not allow this pause reason.');
        }
        if ($reason === SlaPauseReason::AwaitingRequester && $this->firstResponseAt === null) {
            throw new DomainException('Awaiting-requester pause requires a recorded first response.');
        }
        self::assertEvidenceReference($reason, $evidenceReference);

        $this->pauseReason = $reason;
        $this->pauseStartedAt = $at;
        $this->pauseEvidenceReference = trim($evidenceReference);
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    public function resume(DateTimeImmutable $at, int $expectedVersion): int
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        if ($this->pauseReason === null || $this->pauseStartedAt === null || $this->pauseEvidenceReference === null) {
            throw new DomainException('SLA clock is not paused.');
        }

        $pausedWorkingMinutes = $this->calendar->workingMinutesBetween($this->pauseStartedAt, $at);
        if ($this->firstResponseAt !== null) {
            $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($this->nextUpdateDeadline, $pausedWorkingMinutes);
        }
        $this->resolutionDeadline = $this->calendar->addWorkingMinutes($this->resolutionDeadline, $pausedWorkingMinutes);

        $this->pauseHistory[] = [
            'reason' => $this->pauseReason->value,
            'evidence_reference' => $this->pauseEvidenceReference,
            'started_at' => $this->pauseStartedAt->format(DATE_ATOM),
            'resumed_at' => $at->format(DATE_ATOM),
            'working_minutes' => $pausedWorkingMinutes,
        ];

        $this->pauseReason = null;
        $this->pauseStartedAt = null;
        $this->pauseEvidenceReference = null;
        $this->lastMutationAt = $at;
        ++$this->version;
        return $pausedWorkingMinutes;
    }

    public function resolve(DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        if ($this->resolvedAt !== null) {
            throw new DomainException('SLA clock is already resolved.');
        }
        $this->resolvedAt = $at;
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    public function status(DateTimeImmutable $at): string
    {
        if ($this->resolvedAt !== null) {
            return 'completed';
        }
        if ($this->pauseReason !== null) {
            return 'paused';
        }
        if ($at > $this->resolutionDeadline) {
            return 'breached_resolution';
        }
        if ($this->firstResponseAt === null && $at > $this->firstResponseDeadline) {
            return 'breached_first_response';
        }
        if ($this->firstResponseAt !== null && $at > $this->nextUpdateDeadline) {
            return 'breached_update';
        }

        $remaining = $this->remainingResolutionWorkingMinutes($at);
        $warningAt = (int) ceil($this->policy->resolutionMinutes() * (100 - $this->policy->warningThresholdPercent()) / 100);
        return $remaining <= $warningAt ? 'at_risk' : 'on_track';
    }

    public function remainingResolutionWorkingMinutes(DateTimeImmutable $at): int
    {
        if ($at >= $this->resolutionDeadline) {
            return 0;
        }
        return $this->calendar->workingMinutesBetween($at, $this->resolutionDeadline);
    }

    /** @return array<string, mixed> */
    public function snapshot(DateTimeImmutable $at): array
    {
        return [
            'policy_id' => $this->policy->policyId(),
            'policy_version' => $this->policy->version(),
            'status' => $this->status($at),
            'version' => $this->version,
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'first_response_deadline' => $this->firstResponseDeadline->format(DATE_ATOM),
            'next_update_deadline' => $this->nextUpdateDeadline->format(DATE_ATOM),
            'resolution_deadline' => $this->resolutionDeadline->format(DATE_ATOM),
            'remaining_resolution_working_minutes' => $this->remainingResolutionWorkingMinutes($at),
            'pause_reason' => $this->pauseReason?->value,
        ];
    }

    public function version(): int { return $this->version; }
    public function policy(): SlaPolicy { return $this->policy; }
    public function firstResponseDeadline(): DateTimeImmutable { return $this->firstResponseDeadline; }
    public function nextUpdateDeadline(): DateTimeImmutable { return $this->nextUpdateDeadline; }
    public function resolutionDeadline(): DateTimeImmutable { return $this->resolutionDeadline; }
    public function firstResponseAt(): ?DateTimeImmutable { return $this->firstResponseAt; }
    public function resolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }
    public function isPaused(): bool { return $this->pauseReason !== null; }
    /** @return list<array<string, string|int>> */ public function pauseHistory(): array { return $this->pauseHistory; }

    private function assertNotPaused(): void
    {
        if ($this->pauseReason !== null) {
            throw new DomainException('Paused SLA clock must be resumed before this action.');
        }
    }

    private function assertMutableAt(DateTimeImmutable $at): void
    {
        if ($at < $this->startedAt || $at < $this->lastMutationAt) {
            throw new DomainException('SLA mutation timestamp is backdated.');
        }
    }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale SLA version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }

    private static function assertEvidenceReference(SlaPauseReason $reason, string $reference): void
    {
        $prefix = match ($reason) {
            SlaPauseReason::AwaitingRequester => 'message:',
            SlaPauseReason::AwaitingNativeOwner => 'command:',
            SlaPauseReason::ApprovedIncidentDependency => 'incident:',
        };
        if (!str_starts_with(trim($reference), $prefix) || strlen(trim($reference)) <= strlen($prefix)) {
            throw new InvalidArgumentException('Pause evidence reference does not match the governed reason.');
        }
    }
}
