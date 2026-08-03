<?php

declare(strict_types=1);

namespace Sabri\CF02\Resolution;

use DateTimeImmutable;
use InvalidArgumentException;

final class ResolutionDecision
{
    /** @param list<string> $actions */
    public function __construct(
        private readonly ResolutionCode $code,
        private readonly array $actions,
        private readonly ?string $nativeOutcomeReference,
        private readonly string $userInstructions,
        private readonly bool $outcomeVerified,
        private readonly DateTimeImmutable $reopenUntil,
        private readonly bool $closureNoticeSent,
        private readonly bool $autoCloseEligible
    ) {
        if ($actions === [] || trim($userInstructions) === '') {
            throw new InvalidArgumentException('Resolution actions and user instructions are required.');
        }

        foreach ($actions as $action) {
            if (!is_string($action) || trim($action) === '') {
                throw new InvalidArgumentException('Invalid resolution action.');
            }
        }

        if ($code === ResolutionCode::NativeOwnerActionCompleted && ($nativeOutcomeReference === null || trim($nativeOutcomeReference) === '')) {
            throw new InvalidArgumentException('Native-owner outcome reference is required.');
        }
    }

    public function code(): ResolutionCode { return $this->code; }
    /** @return list<string> */ public function actions(): array { return $this->actions; }
    public function nativeOutcomeReference(): ?string { return $this->nativeOutcomeReference; }
    public function userInstructions(): string { return $this->userInstructions; }
    public function outcomeVerified(): bool { return $this->outcomeVerified; }
    public function reopenUntil(): DateTimeImmutable { return $this->reopenUntil; }
    public function closureNoticeSent(): bool { return $this->closureNoticeSent; }
    public function autoCloseEligible(): bool { return $this->autoCloseEligible; }
}
