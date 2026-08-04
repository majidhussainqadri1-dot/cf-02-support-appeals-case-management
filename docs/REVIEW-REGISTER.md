# CF-02 Review and Correction Register

## C2-A through C2-H retained evidence

C2-A through C2-H retain their foundation suites and two independent review/fix suites covering activation, ownership, intake, cases, attachments, assignment, SLA, incidents, authorization, native commands, appeals, quality, automation, migration, WordPress schema, recovery and release gates.

## C2-I — Complete central-plan and file-plan runtime integration

### Review Round 1 — Completeness, persistence and authorization

Status: corrected and retested.

1. Exposed every governed command/query through a versioned WordPress runtime instead of leaving capabilities as detached domain classes.
2. Added the exact 33-command, 20-query contract catalogue and native-owner allowlist.
3. Bound every authenticated context to a File 00 assertion for the exact WordPress principal; removed WP-role inference.
4. Added strict assertion timestamps/lifetime, suspension, representation and recent-auth checks.
5. Added strong ETag and idempotency collision rejection.
6. Completed schema 1.2 payload, event, representative, link, receipt, token, merge, incident, metric and note-history persistence.
7. Completed attachment Uploaded → Quarantined → Scanned → Available/Rejected → Redacted delivery law.
8. Made task, hold, appeal, configuration, merge/split and linked-object evidence transactional/idempotent.
9. Made retention fail closed without an approved active schedule and preserved post-purge reconciliation evidence.
10. Minimized requester attachment projections and prevented sensitive-description persistence before validation.

### Review Round 2 — Fresh adversarial authority, replay and workers

Status: corrected and retested.

1. Removed an invalid event-queue SQL alias.
2. Blocked mutation of terminal native command results and required an outcome reference for success.
3. Made definitive native failure terminal; preserved bounded retries only for unavailable/uncertain delivery.
4. Added synchronous native-result reconciliation events.
5. Added SLA at-risk/breach events and checked optimistic timer updates.
6. Added signed attachment-redaction reconciliation and redaction events.
7. Rejected invented native-owner keys.
8. Preserved reversible merge/split/re-merge history.
9. Added immutable linked-domain projection events.
10. Added an activated WordPress runtime smoke in addition to the dormant lifecycle smoke.

## Automated exact-head scope

The final head must pass PHP 8.1–8.4, all C2-A–I suites, release tests, secret safety, deterministic rebuild, packaged syntax, dormant lifecycle and active-runtime schema/routes/intake/replay/persistence.

## Truthful boundary

The repository may be declared code-complete only after exact-head runs pass. Hostinger staging, real companions/providers, browser/accessibility/security/load testing, production migration, restore/rollback and operational acceptance remain external gates. Any new defect reopens the cycle.
