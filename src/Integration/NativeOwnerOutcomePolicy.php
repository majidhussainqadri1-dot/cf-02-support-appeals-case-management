<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use RuntimeException;

/**
 * A native owner remains the source of truth. Both signed callbacks and
 * background dispatch responses must satisfy the same minimum evidence law.
 * This validates a result envelope, not its remote authenticity.
 */
final class NativeOwnerOutcomePolicy
{
    /**
     * @param array<string,mixed> $result
     * @return array{state:string,native_version:int,outcome_ref:string}
     */
    public static function validate(array $result, int $expectedVersion): array
    {
        if ($expectedVersion < 1) {
            throw new RuntimeException('Native owner expected version is invalid.');
        }
        $state = $result['state'] ?? null;
        if (!is_string($state) || !in_array($state, ['succeeded','failed','outcome_uncertain'], true)) {
            throw new RuntimeException('Native owner result state is unsupported.');
        }
        $version = NativeResultVersion::fromPayload($result['native_version'] ?? null);
        if ($version < $expectedVersion) {
            throw new RuntimeException('Native owner result is stale.');
        }
        $reference = $result['outcome_ref'] ?? '';
        if (!is_string($reference) || strlen($reference) > 191
            || $reference !== trim($reference)
            || preg_match('//u', $reference) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
            throw new RuntimeException('Native owner outcome reference is invalid.');
        }
        if ($state === 'succeeded' && $reference === '') {
            throw new RuntimeException('Successful native owner result requires an outcome reference.');
        }
        return ['state'=>$state,'native_version'=>$version,'outcome_ref'=>$reference];
    }
}
