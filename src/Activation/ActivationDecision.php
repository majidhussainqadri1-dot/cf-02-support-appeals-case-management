<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

final class ActivationDecision
{
    /**
     * @param list<string> $reasons
     * @param array<string, mixed> $evidence
     */
    private function __construct(
        private readonly bool $allowed,
        private readonly array $reasons,
        private readonly array $evidence
    ) {
    }

    /** @param array<string, mixed> $evidence */
    public static function allow(array $evidence): self
    {
        return new self(true, [], $evidence);
    }

    /**
     * @param list<string> $reasons
     * @param array<string, mixed> $evidence
     */
    public static function deny(array $reasons, array $evidence): self
    {
        return new self(false, $reasons, $evidence);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return $this->reasons;
    }

    /** @return array<string, mixed> */
    public function evidence(): array
    {
        return $this->evidence;
    }
}
