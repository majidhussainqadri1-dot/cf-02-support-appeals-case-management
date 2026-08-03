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
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid queue key.');
        }

        if (trim($label) === '' || trim($ownerRole) === '' || trim($scheduleReference) === '' || trim($escalationRole) === '') {
            throw new InvalidArgumentException('Queue ownership and schedule data are required.');
        }

        if ($categoryKeys === [] || $requiredSkills === []) {
            throw new InvalidArgumentException('Queue categories and skills are required.');
        }
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    /** @return list<string> */ public function categoryKeys(): array { return $this->categoryKeys; }
    /** @return list<string> */ public function requiredSkills(): array { return $this->requiredSkills; }
    public function ownerRole(): string { return $this->ownerRole; }
    public function scheduleReference(): string { return $this->scheduleReference; }
    public function escalationRole(): string { return $this->escalationRole; }
    public function sensitive(): bool { return $this->sensitive; }
}
