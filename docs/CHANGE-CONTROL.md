# CF-02 Change-Control Register

## CF02-CCR-0001 — Controlled repository foundation

| Field | Value |
|---|---|
| Requested by | Founder instruction to begin repository implementation |
| Recorded at | 2026-08-03T17:41:00+05:00 |
| Affected files | CF-02 repository; Files 00, 09, 17, 18, 20, 21, 24 and 25 contract boundaries |
| Old rule | Planning document existed; repository had no governed implementation baseline |
| New rule | Phase C2-A dormant foundation with activation evidence, contracts, taxonomy, queues, staffing and traceability |
| Data impact | None; no runtime data processing |
| Security/privacy impact | Fail closed; sensitive queues purpose separated |
| Migration/rollback | No foundation migration; revert branch while `main` remains unchanged |
| Requirement IDs | CF02-FR-006, 008, 013, 016, 019, 024, 030, 032, 033, 034 |
| Approval status | Implementation authorized; runtime activation not approved |

## CF02-CCR-0002 — C2-B intake and case-work foundation

| Field | Value |
|---|---|
| Requested by | Founder instruction to continue implementation |
| Recorded at | 2026-08-03T19:30:00+05:00 |
| Affected files | CF-02 domain, intake, thread, attachment, receipt, deduplication, resolution and portal projections |
| Old rule | C2-B requirements were specified but not represented in code |
| New rule | Add dormant pure-domain foundations for guided intake, replay control, case aggregate, communication separation, secure attachment lifecycle, reversible dedupe, resolution/closure/reopen and user projection |
| Rationale | Implement the next approved phase without prematurely creating an operational support service |
| Data impact | No WordPress tables, routes, uploads or production records; in-memory domain models and tests only |
| Security/privacy impact | Reject secrets, payload collisions, cross-case attachments, scan spoofing, internal-note leakage, raw staff references and reopen bypasses |
| Sharīʿah impact | No new substantive ruling; dignity, honesty, non-deception and safety remain governing |
| Migration plan | Persistence migration deferred until schema phase; later migration requires dry run, reconciliation and rollback |
| Rollback plan | Revert C2-B commits on the development branch; runtime remains dormant |
| Test plan | C2-A suite, C2-B foundation suite, first review regressions, fresh adversarial second-review suite, PHP 8.1–8.4 CI |
| Requirement IDs | CF02-FR-001, 002, 003, 004, 006, 007, 009, 010, 011, 012, 024, 025, 032 |
| Approval status | Implementation authorized; staging/live/runtime activation not approved |

Neither record constitutes permission to activate CF-02. Runtime approval still requires measured extraction need, staffing, real owner contracts, privacy/security review, migration, rollback, staging and Founder acceptance.
