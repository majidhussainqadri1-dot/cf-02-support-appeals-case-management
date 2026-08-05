<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use DateTimeImmutable;
use InvalidArgumentException;

final class ProgressiveRateLimiter
{
    /** @var array<string, list<int>> */
    private array $attempts = [];
    /** @var array<string, int> */
    private array $penaltyUntil = [];

    public function decide(
        string $principalKey,
        string $action,
        DateTimeImmutable $at,
        bool $authenticated,
        bool $emergencySignal = false
    ): array {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{2,190}$/', $principalKey) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $action) !== 1) {
            throw new InvalidArgumentException('Invalid rate-limit principal or action.');
        }
        $key = hash('sha256', $principalKey . "\0" . $action);
        $now = $at->getTimestamp();
        $windowSeconds = 600;
        $this->attempts[$key] = array_values(array_filter(
            $this->attempts[$key] ?? [],
            static fn (int $timestamp): bool => $timestamp > $now - $windowSeconds
        ));

        $penalty = $this->penaltyUntil[$key] ?? 0;
        if ($penalty > $now) {
            return [
                'allowed' => false,
                'retry_after_seconds' => $penalty - $now,
                'challenge_required' => false,
                'emergency_diversion_visible' => $emergencySignal,
                'reason' => 'Progressive abuse penalty remains active.',
            ];
        }

        $softLimit = $authenticated ? 20 : 5;
        $hardLimit = $authenticated ? 40 : 10;
        $count = count($this->attempts[$key]);
        if ($count >= $hardLimit) {
            $multiplier = min(6, max(1, intdiv($count, $hardLimit)));
            $duration = 300 * (2 ** ($multiplier - 1));
            $this->penaltyUntil[$key] = $now + $duration;
            return [
                'allowed' => false,
                'retry_after_seconds' => $duration,
                'challenge_required' => false,
                'emergency_diversion_visible' => $emergencySignal,
                'reason' => 'Hard rate threshold exceeded; bounded progressive penalty applied.',
            ];
        }
        if ($count >= $softLimit) {
            return [
                'allowed' => false,
                'retry_after_seconds' => 60,
                'challenge_required' => true,
                'emergency_diversion_visible' => $emergencySignal,
                'reason' => 'Soft threshold exceeded; human-verification challenge required.',
            ];
        }

        $this->attempts[$key][] = $now;
        return [
            'allowed' => true,
            'retry_after_seconds' => 0,
            'challenge_required' => false,
            'emergency_diversion_visible' => $emergencySignal,
            'remaining_before_challenge' => max(0, $softLimit - count($this->attempts[$key])),
            'reason' => 'Request is within the bounded rate window.',
        ];
    }

    public function forgive(string $principalKey, string $action): void
    {
        $key = hash('sha256', $principalKey . "\0" . $action);
        unset($this->attempts[$key], $this->penaltyUntil[$key]);
    }
}
