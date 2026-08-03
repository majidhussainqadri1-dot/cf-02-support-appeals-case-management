<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use InvalidArgumentException;

final class AppealEligibilityDecision
{
    /** @param list<string> $reasons */
    public function __construct(
        private readonly bool $eligible,
        private readonly bool $exceptionApplied,
        private readonly array $reasons,
        private readonly ?string $furtherPath
    ) {
        if ($reasons === []) {
            throw new InvalidArgumentException('Appeal eligibility requires reasoned findings.');
        }
        if (!$eligible && trim((string) $furtherPath) === '') {
            throw new InvalidArgumentException('Ineligible appeal requires a further path or final explanation.');
        }
    }

    public function eligible(): bool { return $this->eligible; }
    public function exceptionApplied(): bool { return $this->exceptionApplied; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    public function furtherPath(): ?string { return $this->furtherPath; }
}
