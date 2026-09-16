<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

final class CaseStateMachine
{
    /** @var array<string, list<CaseState>> */
    private const TRANSITIONS = [
        'new' => [CaseState::Triaged, CaseState::Withdrawn],
        'triaged' => [CaseState::InProgress, CaseState::WaitingForUser, CaseState::WaitingForProvider, CaseState::Withdrawn],
        'in_progress' => [CaseState::WaitingForUser, CaseState::WaitingForProvider, CaseState::Resolved, CaseState::Withdrawn],
        'waiting_user' => [CaseState::InProgress, CaseState::Resolved, CaseState::Withdrawn],
        'waiting_provider' => [CaseState::InProgress, CaseState::Resolved, CaseState::Withdrawn],
        'resolved' => [CaseState::Closed, CaseState::Reopened],
        'closed' => [CaseState::Reopened],
        'reopened' => [CaseState::InProgress, CaseState::WaitingForUser, CaseState::WaitingForProvider, CaseState::Resolved],
        'withdrawn' => [CaseState::Reopened],
    ];

    public function canTransition(CaseState $from, CaseState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertTransition(CaseState $from, CaseState $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw InvalidTransition::between($from, $to);
        }
    }

    /** @return list<CaseState> */
    public function allowedTargets(CaseState $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }
}
