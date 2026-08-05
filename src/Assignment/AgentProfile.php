<?php

declare(strict_types=1);

namespace Sabri\CF02\Assignment;

use InvalidArgumentException;
use Sabri\CF02\Staffing\StaffingRole;

final class AgentProfile
{
    /** @param list<string> $skills @param list<string> $languages @param list<string> $queueKeys */
    public function __construct(
        private readonly string $agentReference,
        private readonly StaffingRole $role,
        private readonly array $skills,
        private readonly array $languages,
        private readonly array $queueKeys,
        private readonly int $capacity,
        private readonly int $activeCases,
        private readonly bool $onDuty,
        private readonly bool $sensitiveClearance = false
    ) {
        if (trim($agentReference) === '') {
            throw new InvalidArgumentException('Agent reference is required.');
        }

        self::assertIdentifiers($skills, 'skill');
        self::assertLanguages($languages);
        self::assertIdentifiers($queueKeys, 'queue');

        if ($capacity < 1 || $activeCases < 0 || $activeCases > $capacity) {
            throw new InvalidArgumentException('Agent capacity and active-case count are invalid.');
        }

        if ($sensitiveClearance && $role !== StaffingRole::SensitiveLiaison) {
            throw new InvalidArgumentException('Sensitive clearance is reserved for the sensitive liaison role.');
        }
    }

    public function agentReference(): string { return $this->agentReference; }
    public function role(): StaffingRole { return $this->role; }
    /** @return list<string> */ public function skills(): array { return $this->skills; }
    /** @return list<string> */ public function languages(): array { return $this->languages; }
    /** @return list<string> */ public function queueKeys(): array { return $this->queueKeys; }
    public function capacity(): int { return $this->capacity; }
    public function activeCases(): int { return $this->activeCases; }
    public function onDuty(): bool { return $this->onDuty; }
    public function sensitiveClearance(): bool { return $this->sensitiveClearance; }
    public function available(): bool { return $this->onDuty && $this->activeCases < $this->capacity; }
    public function loadRatio(): float { return $this->activeCases / $this->capacity; }

    public function supportsQueue(string $queueKey): bool
    {
        return in_array($queueKey, $this->queueKeys, true);
    }

    /** @param list<string> $requiredSkills */
    public function hasSkills(array $requiredSkills): bool
    {
        return array_diff($requiredSkills, $this->skills) === [];
    }

    public function supportsLanguage(string $language): bool
    {
        return in_array('*', $this->languages, true) || in_array($language, $this->languages, true);
    }

    /** @param list<string> $values */
    private static function assertIdentifiers(array $values, string $label): void
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf('Agent %s list is required.', $label));
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid agent %s.', $label));
            }
        }
        if (count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException(sprintf('Duplicate agent %s values are prohibited.', $label));
        }
    }

    /** @param list<string> $languages */
    private static function assertLanguages(array $languages): void
    {
        if ($languages === []) {
            throw new InvalidArgumentException('Agent language list is required.');
        }
        foreach ($languages as $language) {
            if (!is_string($language) || ($language !== '*' && preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language) !== 1)) {
                throw new InvalidArgumentException('Invalid agent language identifier.');
            }
        }
        if (count($languages) !== count(array_unique($languages))) {
            throw new InvalidArgumentException('Duplicate agent languages are prohibited.');
        }
    }
}
