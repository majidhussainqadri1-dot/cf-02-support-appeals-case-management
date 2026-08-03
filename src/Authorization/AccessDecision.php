<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use InvalidArgumentException;

final class AccessDecision
{
    /** @param list<string> $reasons */
    public function __construct(
        private readonly bool $allowed,
        private readonly array $reasons,
        private readonly string $policyVersion = 'cf02-access-v1'
    ) {
        if ($reasons === []) {
            throw new InvalidArgumentException('Access decision requires at least one reason.');
        }
        foreach ($reasons as $reason) {
            if (!is_string($reason) || trim($reason) === '') {
                throw new InvalidArgumentException('Access-decision reasons must be non-empty strings.');
            }
        }
    }

    public static function allow(string ...$reasons): self
    {
        return new self(true, $reasons === [] ? ['Access requirements satisfied.'] : $reasons);
    }

    public static function deny(string ...$reasons): self
    {
        return new self(false, $reasons === [] ? ['Access requirements were not satisfied.'] : $reasons);
    }

    public function allowed(): bool { return $this->allowed; }
    /** @return list<string> */ public function reasons(): array { return $this->reasons; }
    public function policyVersion(): string { return $this->policyVersion; }
}
