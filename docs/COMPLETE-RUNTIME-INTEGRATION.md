# CF-02 Complete Central-Plan and File-Plan Runtime Integration

## Governing identity

- Central constitution: `SSH-PMP-2026-v3.0`
- CF-02 plan: `1.0 — Four-Round Reviewed and Corrected Final`
- Plugin candidate: `1.0.0-rc.5`
- Schema: `1.3.0`
- Public contract: `1.0.0`

This record covers code-level completion. Hostinger staging, real companion/provider acceptance, deployed security/accessibility/load evidence, production migration, restore/rollback and operational acceptance remain separate evidence states.

## Complete code surface

The runtime now implements the full documented CF-02 contract instead of leaving major plan capabilities as domain-only classes:

1. **Canonical contract catalogue** — 33 commands, 20 queries, governed events, 12 support categories, native-owner keys and role-capability maps.
2. **File 00 authorization** — exact authenticated-actor binding, versioned assertions, maximum lifetime, suspension, representative scope, recent authentication and capability checks; no staff privilege inferred from WordPress role names.
3. **Case intake and work** — guided intake, safe descriptions, emergency diversion, exact replay, receipt outbox, requester/staff projections, assignment, transfer, escalation, waiting, resolution, closure, reopen, notes, revisions, tasks and reversible merges.
4. **Evidence and attachments** — upload intent, quarantine, signed scan result, clean/rejected result, signed redaction, one-time delivery tokens, restricted projections, holds and purge reconciliation.
5. **SLA and operations** — versioned policy provider, timers, pause/resume, at-risk/breach events, escalation requests, backlog/SLA/reopen/quality metrics and scheduled workers.
6. **Appeals** — submission, immutable dossier, eligibility, conflict facts, reviewer assignment, reasoned outcomes, native action request, implementation confirmation, remand and closure.
7. **Native-owner integration** — encrypted command payloads, expected native version, owner allowlist, retry/dead-letter/outcome-uncertain handling, terminal-result immutability and reconciliation events; no direct writes to companion data.
8. **Configuration and retention** — staged versioned configuration, separation of duties, dual approval, activation, rollback, approved retention schedule, hold-aware purge and preserved minimal audit/event evidence.
9. **WordPress runtime** — idempotent schema installer, both `/cf02/v1` and `/api/support/v1` REST namespaces, canonical route contracts for File 20, public/private surfaces, no-store/noindex headers, roles, schedules and non-destructive uninstall.
10. **Security/privacy** — authenticated encryption, strong ETag, idempotency collisions, signed provider requests, replay window, secret/card/OTP detection, purpose-bound access, minimized requester projection and tamper-evident events/audit.

## Review and correction rounds

### C2-I Review 1 — Runtime completeness and persistence integrity

Corrected:

- code models without runtime command/query exposure;
- staff authority accidentally inferable from WordPress roles;
- File 00 assertion actor spoofing and permissive timestamp parsing;
- weak ETag acceptance;
- case creation before sensitive-description validation;
- incomplete attachment Uploaded → Quarantined → Scanned → Available/Rejected/Redacted lifecycle;
- non-transactional task, hold, appeal, configuration and merge evidence;
- immediate/default retention behavior without an approved schedule;
- requester attachment hash/reference disclosure;
- missing post-purge authorized reconciliation access.

### C2-I Review 2 — Fresh adversarial ownership, replay and worker review

Corrected:

- invalid event queue SQL alias;
- changed native callback after terminal result;
- successful native result without outcome reference;
- definitive native failure treated as endless retry;
- missing synchronous native reconciliation event;
- missing SLA at-risk/breach domain events;
- unsigned attachment redaction callback;
- native-owner key invention;
- active merge-history uniqueness that blocked governed split/re-merge history;
- linked-domain projection changes without an immutable event.

## Automated evidence constitution

The exact-head CI must pass:

- PHP 8.1, 8.2, 8.3 and 8.4;
- Composer validation and all PHP syntax;
- C2-A through C2-H foundation + two review suites;
- C2-I complete-runtime foundation + two review suites;
- release-engineering tests and public repository safety scan;
- deterministic package rebuild and source/package parity;
- fail-closed dormant lifecycle smoke;
- activated WordPress runtime smoke covering schema, routes, intake, replay collision, SLA, event, outbox and encrypted-message persistence.

## Truthful completion state

| State | Result |
|---|---|
| Specified | complete |
| Coded | complete within the two governing plans |
| Two fresh review/fix rounds after final coding | complete |
| Packaged | generated only after exact-head workflows pass |
| Automated-QA Green | established only by exact-head workflows |
| Hostinger staging accepted | not yet |
| Live deployed | no |
| Operational | no |
