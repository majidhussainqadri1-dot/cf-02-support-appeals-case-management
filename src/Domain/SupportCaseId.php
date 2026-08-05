<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

use InvalidArgumentException;

final class SupportCaseId
{
    private function __construct(private readonly string $value)
    {
        if (preg_match('/^CF02-[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid CF-02 case identifier.');
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = strtoupper(bin2hex($bytes));

        return new self(sprintf(
            'CF02-%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        ));
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
