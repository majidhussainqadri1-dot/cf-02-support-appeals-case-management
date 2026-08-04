# Changelog

All notable CF-02 repository changes are recorded here. Status words follow the platform's truthful completion law: coded, packaged, automated-QA, staging-accepted, live-deployed and operational are separate states.

## 1.0.0-rc.3 — Complete central-plan and CF-02-plan coded runtime candidate

### Added

- Canonical public catalogue for all 33 commands, 20 queries, events, categories, native owners and role capabilities.
- Complete WordPress REST runtime under both `cf02/v1` and `api/support/v1`.
- Exact File 00 actor-bound, versioned, expiring and suspension-aware authorization assertions.
- Strong optimistic concurrency, replay/idempotency collision checks and purpose-bound mutation evidence.
- Runtime persistence for encrypted command/outbox payloads, events, representatives, linked objects, inbound receipts, one-time attachment tokens, merge history, incident links, metrics and note revisions.
- Signed inbound, scan, redaction and native-result provider adapters.
- Concrete workers for event publication, File 19 delivery, native reconciliation, SLA and retention.
- Complete case, attachment, task, hold, appeal, configuration, merge/split, quality and retention runtime workflows.
- Active-runtime WordPress integration workflow in addition to the fail-closed dormant lifecycle workflow.
- `docs/COMPLETE-RUNTIME-INTEGRATION.md` and updated traceability/readiness evidence.

### Corrected during Review 1

- Replaced limited REST exposure with the full governed command/query surface.
- Prevented staff authority from being inferred from WordPress role labels.
- Bound File 00 assertions to the exact authenticated WordPress principal and strict timestamps.
- Rejected weak ETags and sensitive case descriptions before persistence.
- Completed attachment quarantine, scan, verdict, redaction and one-time delivery states.
- Made task, hold, appeal, configuration and merge evidence transactional/idempotent.
- Made retention fail closed without an approved active schedule.
- Minimized requester attachment projections and preserved authorized post-purge reconciliation.

### Corrected during fresh adversarial Review 2

- Removed an invalid SQL alias from the event worker query.
- Blocked terminal native-result mutation and required an outcome reference for success.
- Treated definitive native failure as terminal instead of endless retry.
- Added synchronous native reconciliation and SLA at-risk/breach events.
- Added signed redaction callbacks and canonical native-owner validation.
- Preserved reversible merge history and linked-object event evidence.

### Status

- Specified: yes.
- Coded against both governing plans: yes.
- Two fresh review/fix rounds after final coding: yes.
- Automated-QA/package evidence: generated only for the exact final head.
- Hostinger staging accepted: no.
- Live deployed: no.
- Operational: no.

## 1.0.0-rc.2 — Packaged candidate

- Added deterministic, allowlisted WordPress packaging, exact source manifest, SBOM, provenance and checksums.
- Added clean fail-closed WordPress lifecycle verification and non-destructive uninstall.
- Corrected exact-head binding, archive ordering, uninstall-scanner false positive and lifecycle state expectation.

## 1.0.0-rc.1 — Complete domain coding candidate

- Completed C2-A through C2-H domain/repository implementation and two review/fix rounds per phase.
- Added fail-closed WordPress schema foundations.
