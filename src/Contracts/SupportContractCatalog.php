<?php

declare(strict_types=1);

namespace Sabri\CF02\Contracts;

use InvalidArgumentException;

/**
 * Canonical public contract catalogue for CF-02 plan v1.0.
 *
 * This class prevents a controller or companion from inventing undocumented
 * commands, queries, events, categories or owners at runtime.
 */
final class SupportContractCatalog
{
    public const CONTRACT_VERSION = '1.1.0';

    /** @var list<string> */
    private const COMMANDS = [
        'CreateCase', 'AddCaseMessage', 'UploadCaseAttachment', 'WithdrawCase', 'ReopenCase',
        'TriageCase', 'SetPriority', 'AssignCase', 'TransferCase', 'EscalateCase', 'MergeCases', 'SplitMergedCase',
        'AddInternalNote', 'CreateCaseTask', 'CompleteCaseTask', 'RequestUserInfo', 'RequestProviderAction',
        'ResolveCase', 'CloseCase', 'ReopenResolvedCase', 'ApplyCaseHold', 'ReleaseCaseHold',
        'SubmitAppeal', 'DecideAppealEligibility', 'AssignAppealReviewer', 'RecordAppealDecision',
        'RequestNativeDecisionAction', 'ConfirmDecisionImplemented', 'RemandAppeal', 'CloseAppeal',
        'StageSupportConfiguration', 'ActivateSupportConfiguration', 'RollBackSupportConfiguration',
    ];

    /** @var list<string> */
    private const QUERIES = [
        'GetMyCases', 'GetMyCase', 'GetMyAppeal', 'GetCaseReplyOptions',
        'GetAssignedQueue', 'SearchAuthorizedCases', 'GetCaseWorkbench', 'GetSlaAtRisk',
        'GetAppealQueue', 'GetAppealDossier', 'GetNativeDecisionStatus', 'GetConflictCheck',
        'GetBacklogHealth', 'GetSlaMetrics', 'GetReopenMetrics', 'GetQualitySample',
        'GetLinkedDomainProjection', 'ExportCasePackageStatus', 'GetRetentionDue', 'GetPurgeReconciliation',
    ];

    /** @var list<string> */
    private const EVENTS = [
        'SupportCaseCreated', 'SupportCaseTriaged', 'SupportCaseAssigned', 'SupportCaseEscalated',
        'SupportUserReplied', 'SupportAgentReplied', 'SupportCaseWaiting', 'SupportCaseResolved', 'SupportCaseReopened',
        'SupportSlaAtRisk', 'SupportSlaBreached', 'SupportMajorIncidentLinked',
        'AppealSubmitted', 'AppealAccepted', 'AppealRejected', 'AppealReviewerAssigned',
        'AppealDecided', 'AppealImplementationRequested', 'AppealImplemented', 'AppealClosed',
        'SupportAttachmentQuarantined', 'SupportAttachmentAvailable', 'SupportAttachmentRejected', 'SupportAttachmentRedacted', 'SupportSensitiveDataDetected',
        'SupportCaseWithdrawn', 'SupportCaseClosed', 'SupportTaskCreated',
        'SupportNativeCommandRequested', 'SupportNativeCommandResultRecorded', 'SupportRetentionPurgeCompleted',
        'SupportTaskCompleted', 'SupportCaseHoldApplied', 'SupportCaseHoldReleased',
        'SupportCasesMerged', 'SupportCaseMergeReversed', 'SupportQualityReviewRecorded',
        'SupportDomainObjectLinked', 'SupportConfigurationStaged', 'SupportConfigurationActivated', 'SupportConfigurationRolledBack',
    ];

    /** @var list<string> */
    private const CATEGORIES = [
        'account_access', 'verification', 'learning_access', 'publishing', 'clinic_appointment',
        'messages_calls', 'media_pdf', 'marketplace', 'privacy_data_rights', 'safety_abuse',
        'accessibility', 'technical', 'institutional_governance',
    ];

    /** @var array<string,string> */
    private const NATIVE_OWNERS = [
        'identity' => 'File 00/02/09',
        'membership' => 'File 00',
        'messages_reports' => 'File 17',
        'notifications' => 'File 19',
        'shell_routes' => 'File 20',
        'content_moderation' => 'File 21/18',
        'security_assurance' => 'File 24',
        'visual_components' => 'File 25',
        'search_ranking' => 'File 26',
        'payments' => 'CF-03/provider',
        'clinical' => 'File 08/CF-01',
    ];

    /** @var array<string,string> */
    private const LEGACY_CATEGORY_ALIASES = [
        'learning_billing' => 'learning_access',
    ];

    /** @var array<string,list<string>> */
    private const ROLE_CAPABILITIES = [
        'user_reporter' => [
            'case.create', 'case.own.read', 'case.own.reply', 'case.own.attach', 'case.own.withdraw',
            'case.own.reopen', 'appeal.own.submit', 'appeal.own.read', 'feedback.own.submit',
        ],
        'guardian_representative' => [
            'case.represented.read', 'case.represented.reply', 'case.represented.attach',
            'case.represented.reopen', 'appeal.represented.submit', 'appeal.represented.read',
        ],
        'support_agent' => [
            'queue.assigned.read', 'case.assigned.read', 'case.assigned.reply', 'case.assigned.note',
            'case.assigned.task', 'case.assigned.triage', 'case.assigned.resolve', 'case.search.scoped',
        ],
        'specialist_agent' => [
            'queue.specialist.read', 'case.specialist.read', 'case.specialist.reply', 'case.specialist.note',
            'case.specialist.task', 'native.action.request', 'evidence.restricted.read',
        ],
        'team_lead' => [
            'queue.manage', 'case.assign', 'case.transfer', 'case.escalate', 'case.merge', 'case.split',
            'sla.manage', 'metrics.read', 'quality.manage', 'case.search.scoped',
        ],
        'appeal_reviewer' => [
            'appeal.queue.read', 'appeal.review', 'appeal.eligibility', 'appeal.decision',
            'appeal.native.request', 'appeal.implementation.confirm',
        ],
        'privacy_security_clinical_liaison' => [
            'case.sensitive.read', 'case.sensitive.coordinate', 'evidence.restricted.read',
            'hold.apply', 'hold.release', 'native.action.request',
        ],
        'auditor' => [
            'audit.sample.read', 'metrics.privacy_safe.read', 'export.bounded.request',
        ],
        'support_manager' => [
            'configuration.stage', 'configuration.activate', 'configuration.rollback',
            'retention.review', 'reconciliation.manage', 'release.evidence.read', 'repair.inspect', 'repair.execute',
        ],
    ];

    /** @return list<string> */
    public static function commands(): array { return self::COMMANDS; }
    /** @return list<string> */
    public static function queries(): array { return self::QUERIES; }
    /** @return list<string> */
    public static function events(): array { return self::EVENTS; }
    /** @return list<string> */
    public static function categories(): array { return self::CATEGORIES; }
    /** @return array<string,string> */
    public static function legacyCategoryAliases(): array { return self::LEGACY_CATEGORY_ALIASES; }
    public static function normalizeCategory(string $category): string
    {
        $normalized = trim($category);
        return self::LEGACY_CATEGORY_ALIASES[$normalized] ?? $normalized;
    }
    /** @return array<string,string> */
    public static function nativeOwners(): array { return self::NATIVE_OWNERS; }
    /** @return list<string> */ public static function nativeOwnerKeys(): array { return array_keys(self::NATIVE_OWNERS); }

    public static function assertNativeOwnerKey(string $key): void
    {
        if (!array_key_exists($key, self::NATIVE_OWNERS)) {
            throw new InvalidArgumentException('Unknown canonical native-owner key.');
        }
    }
    /** @return array<string,list<string>> */
    public static function roleCapabilities(): array { return self::ROLE_CAPABILITIES; }

    public static function assertCommand(string $name): void
    {
        self::assertIn($name, self::COMMANDS, 'command');
    }

    public static function assertQuery(string $name): void
    {
        self::assertIn($name, self::QUERIES, 'query');
    }

    public static function assertEvent(string $name): void
    {
        self::assertIn($name, self::EVENTS, 'event');
    }

    public static function assertCategory(string $category): void
    {
        self::assertIn(self::normalizeCategory($category), self::CATEGORIES, 'category');
    }

    public static function ownerForDomain(string $domain): string
    {
        if (!array_key_exists($domain, self::NATIVE_OWNERS)) {
            throw new InvalidArgumentException('Unknown native-owner domain.');
        }
        return self::NATIVE_OWNERS[$domain];
    }

    /** @return list<string> */
    public static function capabilitiesForRole(string $role): array
    {
        if (!array_key_exists($role, self::ROLE_CAPABILITIES)) {
            throw new InvalidArgumentException('Unknown CF-02 operational role.');
        }
        return self::ROLE_CAPABILITIES[$role];
    }

    /** @param list<string> $haystack */
    private static function assertIn(string $value, array $haystack, string $kind): void
    {
        if (!in_array($value, $haystack, true)) {
            throw new InvalidArgumentException(sprintf('Unknown CF-02 %s: %s.', $kind, $value));
        }
    }
}
