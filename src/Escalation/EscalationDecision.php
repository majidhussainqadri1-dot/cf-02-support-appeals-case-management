<?php

declare(strict_types=1);

namespace Sabri\CF02\Escalation;

use InvalidArgumentException;

final class EscalationDecision
{
    /** @param list<string> $actions @param list<string> $reasons */
    public function __construct(
        private readonly string $level,
        private readonly array $actions,
        private readonly array $reasons,
        private readonly bool $humanApprovalRequired = true
    ) {
        if (!in_array($level, ['none', 'watch', 'team_lead', 'specialist', 'incident_command'], true)) {
            throw new InvalidArgumentException('Invalid escalation level.');
        }
        if ($actions === [] || $reasons === []) {
            throw new InvalidArgumentException('Escalation actions and reasons are required.');
        }
        foreach (array_merge($actions, $reasons) as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Invalid escalation action or reason.');
            }
        }
    }

    public function level(): string { return $this->level; }
    /** @return list<string> */ public function actions(): array { return $this->actions; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    public function humanApprovalRequired(): bool { return $this->humanApprovalRequired; }
}
