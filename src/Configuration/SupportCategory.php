<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use InvalidArgumentException;

final class SupportCategory
{
    /** @param list<string> $requiredSkills @param list<string> $intakeFields */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $queueKey,
        private readonly array $requiredSkills,
        private readonly string $dataClass,
        private readonly string $nativeOwner,
        private readonly array $intakeFields,
        private readonly bool $specialistOnly = false
    ) {
        self::assertIdentifier($key, 'category');
        self::assertIdentifier($queueKey, 'queue');

        if (trim($label) === '' || trim($nativeOwner) === '') {
            throw new InvalidArgumentException('Category label and native owner are required.');
        }

        if (!in_array($dataClass, ['C1', 'C2', 'C3', 'C4'], true)) {
            throw new InvalidArgumentException('Support category data class must be C1 through C4.');
        }

        self::assertIdentifierList($requiredSkills, 'required skill');
        self::assertIdentifierList($intakeFields, 'intake field');

        foreach ($requiredSkills as $skill) {
            if (!SkillCatalog::exists($skill)) {
                throw new InvalidArgumentException(sprintf('Unknown support skill: %s.', $skill));
            }
        }
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    public function queueKey(): string { return $this->queueKey; }
    /** @return list<string> */ public function requiredSkills(): array { return $this->requiredSkills; }
    public function dataClass(): string { return $this->dataClass; }
    public function nativeOwner(): string { return $this->nativeOwner; }
    /** @return list<string> */ public function intakeFields(): array { return $this->intakeFields; }
    public function specialistOnly(): bool { return $this->specialistOnly; }

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
            throw new InvalidArgumentException(sprintf('At least one %s is required.', $label));
        }

        if (count(array_unique($values)) !== count($values)) {
            throw new InvalidArgumentException(sprintf('Duplicate %s identifiers are not allowed.', $label));
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Every %s must be a string.', $label));
            }
            self::assertIdentifier($value, $label);
        }
    }
}
