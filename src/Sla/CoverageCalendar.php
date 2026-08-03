<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CoverageCalendar
{
    private DateTimeZone $timezone;

    /**
     * @param array<int, list<array{0:string,1:string}>> $weeklyWindows ISO weekday 1-7
     * @param list<string> $holidays YYYY-MM-DD in the calendar timezone
     */
    public function __construct(
        private readonly string $reference,
        string $timezone,
        private readonly array $weeklyWindows,
        private readonly array $holidays = []
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $reference) !== 1) {
            throw new InvalidArgumentException('Invalid coverage calendar reference.');
        }
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid coverage calendar timezone.');
        }
        if ($weeklyWindows === []) {
            throw new InvalidArgumentException('Coverage calendar requires weekly windows.');
        }
        foreach ($weeklyWindows as $weekday => $windows) {
            if (!is_int($weekday) || $weekday < 1 || $weekday > 7 || !is_array($windows) || $windows === []) {
                throw new InvalidArgumentException('Invalid coverage weekday or windows.');
            }
            foreach ($windows as $window) {
                if (!is_array($window) || count($window) !== 2 || !self::validClock($window[0]) || !self::validClock($window[1]) || $window[0] >= $window[1]) {
                    throw new InvalidArgumentException('Invalid coverage time window.');
                }
            }
            $sorted = $windows;
            usort($sorted, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
            $previousEnd = null;
            foreach ($sorted as [$start, $end]) {
                if ($previousEnd !== null && $start < $previousEnd) {
                    throw new InvalidArgumentException('Coverage time windows must not overlap.');
                }
                $previousEnd = $end;
            }
        }
        foreach ($holidays as $holiday) {
            if (!is_string($holiday) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $holiday) !== 1) {
                throw new InvalidArgumentException('Invalid holiday date.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $holiday, $this->timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || $date->format('Y-m-d') !== $holiday || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new InvalidArgumentException('Holiday is not a real calendar date.');
            }
        }
        if (count($holidays) !== count(array_unique($holidays))) {
            throw new InvalidArgumentException('Duplicate holidays are prohibited.');
        }
    }

    public function reference(): string { return $this->reference; }
    public function timezone(): DateTimeZone { return $this->timezone; }

    public function isWorkingMinute(DateTimeImmutable $instant): bool
    {
        $local = $instant->setTimezone($this->timezone);
        if (in_array($local->format('Y-m-d'), $this->holidays, true)) {
            return false;
        }
        $weekday = (int) $local->format('N');
        $clock = $local->format('H:i');
        foreach ($this->weeklyWindows[$weekday] ?? [] as [$start, $end]) {
            if ($clock >= $start && $clock < $end) {
                return true;
            }
        }
        return false;
    }

    public function addWorkingMinutes(DateTimeImmutable $start, int $minutes): DateTimeImmutable
    {
        if ($minutes < 0 || $minutes > 525600) {
            throw new InvalidArgumentException('Working-minute duration is outside the supported range.');
        }
        if ($minutes === 0) {
            return $start;
        }

        $cursor = $start;
        $counted = 0;
        $guard = 0;
        while ($counted < $minutes) {
            $cursor = $cursor->modify('+1 minute');
            if ($this->isWorkingMinute($cursor)) {
                ++$counted;
            }
            if (++$guard > 1051200) {
                throw new InvalidArgumentException('Coverage calendar cannot satisfy the requested duration.');
            }
        }
        return $cursor;
    }

    public function workingMinutesBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if ($end < $start) {
            throw new InvalidArgumentException('Working-minute interval is reversed.');
        }
        $cursor = $start;
        $minutes = 0;
        $guard = 0;
        while ($cursor < $end) {
            $cursor = $cursor->modify('+1 minute');
            if ($cursor <= $end && $this->isWorkingMinute($cursor)) {
                ++$minutes;
            }
            if (++$guard > 1051200) {
                throw new InvalidArgumentException('Working-minute interval exceeds the supported range.');
            }
        }
        return $minutes;
    }

    private static function validClock(mixed $clock): bool
    {
        return is_string($clock) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $clock) === 1;
    }
}
