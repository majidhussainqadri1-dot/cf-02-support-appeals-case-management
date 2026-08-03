<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use InvalidArgumentException;

final class QueueDefinition
{
    /** @param list<string> $categoryKeys @param list<string> $requiredSkills */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly array $categoryKeys,
        private readonly array $requiredSkills,
        private readonly string $ownerRole,
        private readonly string $scheduleReference,
        private readonly string $escalationRole,
        private readonly bool $sensitive = false
    ) {
        self::assertIdentifier($key, 'queue');
        self::assertIdentifier($ownerRole, 'owner role');
        self::assertIdentifier($escalationRole, 'escalation role');

        if (trim($label) === '' || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $scheduleReference) !== 1) {
            throw new InvalidArgumentException('Queue label and a stable schedule reference are required.');
        }

        self::assertIdentifierList($categoryKeys, 'category');
        self::assertIdentifierList($requiredSkills, 'skill');
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    /** @return list<string> */ public function categoryKeys(): array { return $this->categoryKeys; }
    /** @return list<string> */ public function requiredSkills(): array { return $this->requiredSkills; }
    public function ownerRole(): string { return $this->ownerRole; }
    public function scheduleReference(): string { return $this->scheduleReference; }
    public function escalationRole(): string { return $this->escalationRole; }
    public function sensitive(): bool { return $this->sensitive; }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid %s identifier.', $label));
        }
    }

    /** @param list<string> $values */
    private static function assertIdentifierList(array $values, string $label): void
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf('Queue %s values are required.', $label));
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Every queue %s must be a string.', $label));
            }
            self::assertIdentifier($value, $label);
        }

        if (count(array_unique($values)) !== count($values)) {
            throw new InvalidArgumentException(sprintf('Duplicate queue %s values are not allowed.', $label));
        }
    }
}
