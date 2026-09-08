# CF-02 — Support, Appeals and Case Management

Conditional future module for the Sabri Social Homeopathy Platform.

Canonical development home for support intake, ticket/case lifecycle, SLA queues, escalations, evidence-bound appeals, service recovery, and cross-domain case orchestration while preserving each domain’s native decision authority.

## Current repository candidate

- Planning identifier: `CF-02`
- Governing plans: Definitive Master Plan v3.0 + CF-02 plan v1.0 (latest rewritten baselines), with approved later directives where non-conflicting
- Runtime candidate: `1.0.0-rc.6`
- Contract / schema: `1.1.0` / `1.3.0`
- Development stage: `C2-L` latest-two-plans coded candidate
- Runtime: fail-closed
- Staging, live deployment and operational acceptance: **not claimed by repository code, CI or package evidence alone**

## Canonical boundaries

CF-02 owns support cases, queues, SLA timers, case communications, appeal dossiers, implementation reconciliation, service recovery, quality and support-case retention/audit. It does not replace account/verification/moderation/clinical/payment/security/messaging/marketplace native authorities, and cross-domain mutations require native-owner contracts rather than direct foreign writes.

The latest-plan completion batch additionally enforces: donor/popularity/ranking-neutral routing; evidence-minimal typed native references and snapshot/projection hashes; accessible adverse-decision reasons and appeal deadlines; organizationally independent reviewers; separate emergency categories; purpose-bound just-in-time access; reasoned SLA state changes; safe guest pre-intake with authenticated step-up; delivery-failure truth; and privacy-safe monthly donor/non-donor support-parity auditing.

## Release discipline

Every final coding change requires two fresh, separate corrective reviews, full regression, exact-head CI, deterministic package/source parity, and truthful separation of `Coded`, `Automated-QA Green`, `Packaged`, `Staging-Accepted`, `Live-Deployed`, and `Operational` states.

This public repository must not contain secrets, credentials, raw identity evidence, clinical records, payment secrets, private incident playbooks, or production exports.
