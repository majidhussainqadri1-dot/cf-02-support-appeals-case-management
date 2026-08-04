<?php

declare(strict_types=1);

namespace Sabri\CF02\Application;

use DomainException;

/** Runtime state law used by the WordPress command surface. */
final class RuntimeWorkflowPolicy
{
    /** @var array<string,list<string>> */
    private const CASE_TRANSITIONS = [
        'new' => ['triaged', 'withdrawn'],
        'triaged' => ['in_progress', 'waiting_user', 'waiting_provider', 'withdrawn'],
        'in_progress' => ['waiting_user', 'waiting_provider', 'resolved', 'withdrawn'],
        'waiting_user' => ['in_progress', 'resolved', 'withdrawn'],
        'waiting_provider' => ['in_progress', 'resolved', 'withdrawn'],
        'resolved' => ['closed', 'reopened'],
        'closed' => ['reopened'],
        'reopened' => ['in_progress', 'waiting_user', 'waiting_provider', 'resolved'],
        'withdrawn' => ['reopened'],
    ];

    /** @var array<string,list<string>> */
    private const APPEAL_TRANSITIONS = [
        'submitted' => ['eligibility_review'],
        'eligibility_review' => ['accepted', 'rejected'],
        'accepted' => ['under_review'],
        'rejected' => ['reopened'],
        'under_review' => ['native_decision_pending', 'decided'],
        'native_decision_pending' => ['decided'],
        'decided' => ['implemented', 'under_review'],
        'implemented' => ['closed'],
        'closed' => ['reopened'],
        'reopened' => ['eligibility_review', 'under_review'],
    ];

    /** @var array<string,list<string>> */
    private const ATTACHMENT_TRANSITIONS = [
        'uploaded' => ['quarantined'],
        'quarantined' => ['scanned', 'rejected'],
        'scanned' => ['available', 'rejected'],
        'available' => ['redacted', 'superseded', 'expired'],
        'redacted' => ['superseded', 'expired'],
        'superseded' => ['expired'],
        'rejected' => ['expired', 'purged'],
        'expired' => ['purged'],
        'purged' => [],
    ];

    public static function assertCase(string $from, string $to): void
    {
        self::assert(self::CASE_TRANSITIONS, $from, $to, 'case');
    }

    public static function assertAppeal(string $from, string $to): void
    {
        self::assert(self::APPEAL_TRANSITIONS, $from, $to, 'appeal');
    }

    public static function assertAttachment(string $from, string $to): void
    {
        self::assert(self::ATTACHMENT_TRANSITIONS, $from, $to, 'attachment');
    }

    /** @return array<string,list<string>> */
    public static function caseTransitions(): array { return self::CASE_TRANSITIONS; }
    /** @return array<string,list<string>> */
    public static function appealTransitions(): array { return self::APPEAL_TRANSITIONS; }
    /** @return array<string,list<string>> */
    public static function attachmentTransitions(): array { return self::ATTACHMENT_TRANSITIONS; }

    /** @param array<string,list<string>> $law */
    private static function assert(array $law, string $from, string $to, string $aggregate): void
    {
        if (!array_key_exists($from, $law) || !in_array($to, $law[$from], true)) {
            throw new DomainException(sprintf('Illegal %s transition: %s -> %s.', $aggregate, $from, $to));
        }
    }
}
