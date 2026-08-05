<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

final class SkillCatalog
{
    /** @return array<string, string> */
    public static function definitions(): array
    {
        return [
            'account_support' => 'Account access troubleshooting without credential or identity takeover authority.',
            'verification_support' => 'Verification workflow support without native approval authority.',
            'publishing_support' => 'Publishing workflow and status support through native owner contracts.',
            'learning_support' => 'Learning access and course-navigation support.',
            'clinic_support' => 'Clinic and appointment support without clinical advice.',
            'communications_support' => 'Messages and calls support without raw conversation access by default.',
            'media_support' => 'Video, Reel and PDF delivery support.',
            'marketplace_support' => 'Marketplace support without settlement, refund or listing-decision authority.',
            'accessibility_support' => 'Accessibility issue identification and accessible communication.',
            'technical_support' => 'Platform fault reproduction and safe diagnostic collection.',
            'privacy_safe_handling' => 'Purpose-bound handling of personal support data.',
            'privacy_liaison' => 'Controlled privacy-right escalation to the native owner.',
            'safety_liaison' => 'Controlled safety and abuse escalation to the native owner.',
            'institutional_governance' => 'Institutional-governance intake without disciplinary execution authority.',
            'due_process_review' => 'Evidence-based notice, response, conflict, proportionality and appeal review.',
            'rights_safety' => 'Copyright, consent and media-rights triage.',
            'restricted_evidence' => 'Approved access to restricted evidence with logging and expiry.',
        ];
    }

    public static function exists(string $skill): bool
    {
        return array_key_exists($skill, self::definitions());
    }
}
