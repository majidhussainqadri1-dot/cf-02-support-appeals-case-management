<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use InvalidArgumentException;

/**
 * Stable registry for the CF-02 Future Support, Appeals & Case Intelligence 24 addendum.
 *
 * These capabilities are coded foundations only. They do not bypass the normal CF-02
 * activation, authorization, native-owner, staging, deployment, or operational gates.
 */
final class FeatureCatalog
{
    public const VERSION = '1.0';

    /** @var array<string, array{name:string,domain:string,owner:string,activation:string,guardrail:string}> */
    private const FEATURES = [
        'CF02-FUT-001' => ['name'=>'Hidayah Support Copilot','domain'=>'assistance','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Suggestion only; no final appeal, refund, identity, clinical, safety or native-owner decision.'],
        'CF02-FUT-002' => ['name'=>'Pre-Ticket Self-Service Resolver','domain'=>'assistance','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Emergency, privacy, security and sensitive cases cannot be deflected away from governed intake.'],
        'CF02-FUT-003' => ['name'=>'Case Risk Radar','domain'=>'assistance','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Risk uses service signals only; donor, popularity, rank and payment privilege are prohibited.'],
        'CF02-FUT-004' => ['name'=>'Next-Best-Action Engine','domain'=>'assistance','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Returns authorized suggestions only; native-owner actions remain commands to their canonical owners.'],
        'CF02-FUT-005' => ['name'=>'Known Problem & Root-Cause Center','domain'=>'problem-knowledge','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Links cases to a canonical problem without merging, exposing, or auto-closing individual cases.'],
        'CF02-FUT-006' => ['name'=>'Knowledge-Gap Detector','domain'=>'problem-knowledge','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Aggregate evidence only; AI drafts require human publication approval and versioning.'],
        'CF02-FUT-007' => ['name'=>'Appeal Eligibility Preview','domain'=>'appeals','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Preview is non-binding and cannot substitute the governed eligibility decision.'],
        'CF02-FUT-008' => ['name'=>'Appeal Evidence Room','domain'=>'appeals','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Stores typed references, versions and hashes rather than duplicating native-domain truth.'],
        'CF02-FUT-009' => ['name'=>'Decision Difference Viewer','domain'=>'appeals','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Shows reasoned differences while redacting sensitive or non-disclosable evidence.'],
        'CF02-FUT-010' => ['name'=>'Support-Service Complaint / Second-Level Review','domain'=>'appeals','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'The complained-about handler cannot be the final reviewer; conflict-free second-level review is required.'],
        'CF02-FUT-011' => ['name'=>'Secure Co-Browsing','domain'=>'evidence-channel','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Explicit consent, scoped view, expiry and no password, OTP or unrestricted remote-control capture.'],
        'CF02-FUT-012' => ['name'=>'Consent-Based Voice Support','domain'=>'evidence-channel','owner'=>'CF-02 + File 17','activation'=>'feature-gated','guardrail'=>'File 17 remains communication owner; CF-02 stores only governed case linkage and evidence references.'],
        'CF02-FUT-013' => ['name'=>'Client-Side Evidence Sanitizer','domain'=>'evidence-channel','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Warns on secrets/payment data and strips nonessential metadata before governed upload.'],
        'CF02-FUT-014' => ['name'=>'Resumable Large Evidence Upload','domain'=>'evidence-channel','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Chunk integrity, final checksum and malware/content scan must pass before evidence becomes accessible.'],
        'CF02-FUT-015' => ['name'=>'Public Service Status Center','domain'=>'continuity-experience','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Only privacy-safe service incident information may be public; no case/user identifiers.'],
        'CF02-FUT-016' => ['name'=>'Incident Subscription','domain'=>'continuity-experience','owner'=>'CF-02 + File 19','activation'=>'feature-gated','guardrail'=>'Subscription is service/incident scoped and cannot expose reporters or unrelated case data.'],
        'CF02-FUT-017' => ['name'=>'Smart Multilingual Support','domain'=>'continuity-experience','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Translation is a draft; clinical, legal, safety and other high-risk text requires human-reviewed authority.'],
        'CF02-FUT-018' => ['name'=>'Accessibility Support Profile','domain'=>'continuity-experience','owner'=>'CF-02 consumer of identity/preferences','activation'=>'feature-gated','guardrail'=>'Stores support preferences only; identity and representative authority remain with native owners.'],
        'CF02-FUT-019' => ['name'=>'Low-Bandwidth / PWA Case Mode','domain'=>'continuity-experience','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Sensitive C4/C5 evidence cannot be placed in uncontrolled offline caches.'],
        'CF02-FUT-020' => ['name'=>'Native Mobile Support','domain'=>'continuity-experience','owner'=>'CF-02 API; native app shell external','activation'=>'feature-gated','guardrail'=>'Mobile uses the same governed backend and does not create duplicate case truth.'],
        'CF02-FUT-021' => ['name'=>'Secure Deep Links & QR','domain'=>'integration-security','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Short-lived signed opaque token; no raw private case identifier or open redirect.'],
        'CF02-FUT-022' => ['name'=>'Institutional Support API + Webhooks','domain'=>'integration-security','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Scoped authentication, idempotency, signed webhooks, replay protection and secret rotation are mandatory.'],
        'CF02-FUT-023' => ['name'=>'Agent Training & Simulation Lab','domain'=>'operations-transparency','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Synthetic data only; real patient, user, payment, identity or message data is prohibited in training fixtures.'],
        'CF02-FUT-024' => ['name'=>'Transparency & Fairness Center','domain'=>'operations-transparency','owner'=>'CF-02','activation'=>'feature-gated','guardrail'=>'Privacy-thresholded aggregate service metrics only; no covert staff surveillance or low-volume identity disclosure.'],
    ];

    /** @return array<string, array{name:string,domain:string,owner:string,activation:string,guardrail:string}> */
    public static function all(): array
    {
        return self::FEATURES;
    }

    /** @return array{name:string,domain:string,owner:string,activation:string,guardrail:string} */
    public static function get(string $featureId): array
    {
        if (!isset(self::FEATURES[$featureId])) {
            throw new InvalidArgumentException('Unknown CF-02 future feature ID.');
        }
        return self::FEATURES[$featureId];
    }

    public static function count(): int
    {
        return count(self::FEATURES);
    }
}
