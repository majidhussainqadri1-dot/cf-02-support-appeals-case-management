# CF-02 — Support, Appeals and Case Management

Conditional future module for the Sabri Social Homeopathy Platform.

Canonical development home for support intake, ticket/case lifecycle, SLA queues, escalations, evidence-bound appeals, service recovery, and cross-domain case orchestration while preserving each domain’s native decision authority.

## Current repository candidate

- Planning identifier: `CF-02`
- Governing plans: Definitive Master Plan v3.0 + CF-02 plan v1.0 (latest rewritten baselines), with approved later directives where non-conflicting
- Runtime candidate line: `1.0.0-rc.8`; exact source SHA and package checksum remain mandatory release identity
- Contract / schema: `1.3.0` / `1.3.0`
- Development stage: `C2-M` Future Support, Appeals & Case Intelligence 24 coded foundation
- Runtime: fail-closed
- Staging, live deployment and operational acceptance: **not claimed by repository code, CI or package evidence alone**

## Canonical boundaries

CF-02 owns support cases, queues, SLA timers, case communications, appeal dossiers, implementation reconciliation, service recovery, quality and support-case retention/audit. It does not replace account/verification/moderation/clinical/payment/security/messaging/marketplace native authorities, and cross-domain mutations require native-owner contracts rather than direct foreign writes.

The latest-plan completion batch additionally enforces: donor/popularity/ranking-neutral routing; evidence-minimal typed native references and snapshot/projection hashes; accessible adverse-decision reasons and appeal deadlines; organizationally independent reviewers; separate emergency categories; purpose-bound just-in-time access; reasoned SLA state changes; safe guest pre-intake with authenticated step-up; delivery-failure truth; and privacy-safe monthly donor/non-donor support-parity auditing.

## Future Support, Appeals & Case Intelligence 24

C2-M adds coded, feature-gated foundations for `CF02-FUT-001` through `CF02-FUT-024`: Hidayah Support Copilot, pre-ticket self-service, Case Risk Radar, Next-Best-Action, known-problem/root-cause management, knowledge-gap detection, appeal eligibility preview, appeal evidence room, decision-difference view, second-level support complaints, secure co-browsing, consented voice handoff, evidence sanitization, resumable evidence upload, public service status, incident subscriptions, multilingual drafting, accessibility support preferences, low-bandwidth/PWA support, native-mobile envelopes, secure deep links/QR, institutional API/webhook security, synthetic agent training, and aggregate transparency/fairness reporting.

These foundations are deliberately side-effect free and feature-gated. They do not create a second database, bypass CF-02 authorization, automate final appeal/refund/identity/clinical/safety decisions, copy native-domain truth, expose private incident/case data, or turn repository coding into staging/live/operational acceptance. See `docs/FUTURE24-IMPLEMENTATION.md` and `docs/FUTURE24-PLAN-ADDENDUM.md`.

## C2-N — V12.3 corrective user experience and release candidate

The November 2026 V12.3 review adds a safe guest support form and an explicit, signed, ten-minute post-login continuation; the request is **not created** until File 00 authenticates and the user confirms. The guest form restricts categories, disallows secrets and diverts emergencies to the appropriate contact pathway.

Requester and administrative surfaces now use the canonical Sabri Green `#087A4E`, token-aware accessible colors, reduced-motion and adaptive dark appearance. WordPress support administration loads external CSP-friendly JavaScript and CSS, with authorization checked server-side before enqueue and before render. The Urdu PO/POT/MO catalog includes the new strings.

The canonical candidate is `1.0.0-rc.8`, schema `1.3.0`, contract `1.3.0`. The new permanent regressions are `tests/c2n-v12-3-alignment.php`, `tests/c2n-review1.php`, and `tests/c2n-review2.php`; the main and release workflows validate all shipped JavaScript. Read `docs/V12.3-CF02-CODE-AND-ACCEPTANCE-AUDIT.md` for the 74-item baseline and **remaining external acceptance blockers**.

## Release discipline

Every final coding change requires two fresh, separate corrective reviews, full regression, exact-head CI, deterministic package/source parity, and truthful separation of `Coded`, `Automated-QA Green`, `Packaged`, `Staging-Accepted`, `Live-Deployed`, and `Operational` states.

This public repository must not contain secrets, credentials, raw identity evidence, clinical records, payment secrets, private incident playbooks, or production exports.