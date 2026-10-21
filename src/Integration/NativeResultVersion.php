<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use RuntimeException;

/** Exact, non-clamping signed native-owner record version. */
final class NativeResultVersion
{
    public static function fromPayload(mixed $value): int
    {
        if (is_int($value)) {
            if ($value < 1) {
                throw new RuntimeException('Native result version must be positive.');
            }
            return $value;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new RuntimeException('Native result version must be a canonical positive integer.');
        }
        $max = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($max) ||
            (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
            throw new RuntimeException('Native result version exceeds the supported integer range.');
        }
        return (int) $value;
    }
}
