<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

use DomainException;
use Sabri\CF02\Configuration\QueueRegistry;

final class SlaPolicyRegistry
{
    /** @return array<string, SlaPolicy> */
    public static function defaults(): array
    {
        $targets = [
            'P1' => [15, 60, 240, 50],
            'P2' => [60, 240, 1440, 65],
            'P3' => [240, 1440, 4320, 75],
            'P4' => [480, 2880, 10080, 80],
        ];
        $policies = [];
        foreach (QueueRegistry::defaults() as $queue) {
            foreach ($targets as $priority => [$first, $update, $resolution, $warning]) {
                $id = sprintf('%s.%s.v1', $queue->key(), strtolower($priority));
                $policy = new SlaPolicy(
                    $id,
                    '1.0.0',
                    $queue->key(),
                    $priority,
                    $queue->scheduleReference(),
                    $first,
                    $update,
                    $resolution,
                    $warning,
                    [
                        SlaPauseReason::AwaitingRequester,
                        SlaPauseReason::AwaitingNativeOwner,
                        SlaPauseReason::ApprovedIncidentDependency,
                    ]
                );
                $policies[self::key($queue->key(), $priority)] = $policy;
            }
        }
        return $policies;
    }

    public static function resolve(string $queueKey, string $priority): SlaPolicy
    {
        $policy = self::defaults()[self::key($queueKey, $priority)] ?? null;
        if (!$policy instanceof SlaPolicy) {
            throw new DomainException('No active SLA policy matches the queue and priority.');
        }
        return $policy;
    }

    /** @return list<string> */
    public static function validate(): array
    {
        $reasons = [];
        foreach (self::defaults() as $key => $policy) {
            if ($key !== self::key($policy->queueKey(), $policy->priority())) {
                $reasons[] = sprintf('SLA policy index mismatch: %s.', $key);
            }
            if (!isset(QueueRegistry::defaults()[$policy->queueKey()])) {
                $reasons[] = sprintf('SLA policy references unknown queue: %s.', $policy->queueKey());
            }
        }
        return array_values(array_unique($reasons));
    }

    private static function key(string $queueKey, string $priority): string
    {
        return $queueKey . ':' . $priority;
    }
}
