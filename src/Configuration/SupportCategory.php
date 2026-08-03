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
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException('Category key must be a stable lowercase identifier.');
        }

        foreach ([$label, $queueKey, $nativeOwner] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Category label, queue and native owner are required.');
            }
        }

        if (!in_array($dataClass, ['C1', 'C2', 'C3', 'C4'], true)) {
            throw new InvalidArgumentException('Support category data class must be C1 through C4.');
        }

        if ($requiredSkills === [] || $intakeFields === []) {
            throw new InvalidArgumentException('Support category skills and minimum intake fields are required.');
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
}
