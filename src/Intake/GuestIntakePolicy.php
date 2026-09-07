<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use InvalidArgumentException;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Security\SensitiveContentDetector;

final class GuestIntakePolicy
{
    /** @var list<string> */
    private const GUEST_CATEGORIES = ['publishing', 'learning_access', 'media_pdf', 'accessibility', 'technical'];

    /**
     * @param array<string,mixed> $input
     * @return array{category:string,subject:string,description:string,impact:string,urgency:string,locale:string}
     */
    public static function normalize(array $input): array
    {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($input);
        $category = trim((string) ($input['category'] ?? ''));
        if (!isset(SupportTaxonomy::defaults()[$category]) || !in_array($category, self::GUEST_CATEGORIES, true)) {
            throw new InvalidArgumentException('This support category requires authenticated step-up before sensitive disclosure.');
        }
        $subject = trim((string) ($input['subject'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if (strlen($subject) < 5 || strlen($subject) > 191 || strlen($description) > 3000) {
            throw new InvalidArgumentException('Guest support subject or description length is invalid.');
        }
        if (SensitiveContentDetector::containsProhibitedSecret($subject . "\n" . $description)) {
            throw new InvalidArgumentException('Secrets must not be placed in guest support intake.');
        }
        $impact = trim((string) ($input['impact'] ?? 'single_action'));
        $urgency = trim((string) ($input['urgency'] ?? 'normal'));
        if (!in_array($impact, ['single_action', 'many_users'], true)
            || !in_array($urgency, ['normal', 'time_sensitive'], true)) {
            throw new InvalidArgumentException('Guest impact or urgency is invalid.');
        }
        $locale = trim((string) ($input['locale'] ?? 'ur-PK'));
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            throw new InvalidArgumentException('Guest locale is invalid.');
        }
        return compact('category', 'subject', 'description', 'impact', 'urgency', 'locale');
    }

    /** @return list<string> */
    public static function categories(): array { return self::GUEST_CATEGORIES; }
}
