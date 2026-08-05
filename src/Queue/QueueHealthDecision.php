<?php

declare(strict_types=1);

namespace Sabri\CF02\Queue;

use InvalidArgumentException;

final class QueueHealthDecision
{
    /** @param list<string> $reasons @param list<string> $actions */
    public function __construct(
        private readonly string $status,
        private readonly array $reasons,
        private readonly array $actions
    ) {
        if (!in_array($status, ['healthy', 'watch', 'critical'], true)) {
            throw new InvalidArgumentException('Invalid queue-health status.');
        }
        if ($reasons === [] || $actions === []) {
            throw new InvalidArgumentException('Queue-health reasons and actions are required.');
        }
    }

    public function status(): string { return $this->status; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    /** @return list<string> */ public function actions(): array { return $this->actions; }
}
