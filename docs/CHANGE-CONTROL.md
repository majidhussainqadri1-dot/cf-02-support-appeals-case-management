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

## CF02-CCR-0003 — C2-C assignment, SLA and incident foundation

| Field | Value |
|---|---|
| Requested by | Founder instruction to continue to the next phase |
| Recorded at | 2026-08-03T23:51:00+05:00 |
| Affected files | Assignment, collaboration, SLA policies/calendars/clocks, breach prediction, escalation, queue health and major-incident linkage |
| Old rule | C2-C capabilities were specified; only static queues and staffing roles existed |
| New rule | Add dormant pure-domain foundations for one accountable owner, eligibility routing, short-lived assignment decisions, scoped collaboration, versioned SLA targets, evidenced pause/resume, breach prediction, human-governed escalation, fresh queue metrics and independent-case incident linkage |
| Rationale | Establish enforceable ownership and time-management law before persistence, workers or user-facing operations are introduced |
| Data impact | No WordPress tables, cron jobs, routes, notifications, production staffing data or incident records; in-memory contracts and tests only |
| Security/privacy impact | Exact language/skill/role eligibility; restricted access requires explicit approval; stale assignments/metrics fail closed; SLA evidence is typed and unique; public incident data excludes secrets and internal notice references |
| Sharīʿah impact | No new substantive ruling; accountability, fulfilment of commitments, non-deception, privacy and prevention of harm remain governing |
| Migration plan | Persistence and timer migration deferred; later schema must preserve policy version, deadlines, pause evidence, assignment history and incident links with reconciliation |
| Rollback plan | Revert C2-C commits on the development branch; `main`, staging and live remain unchanged |
| Test plan | Full C2-A/C2-B regression suite, C2-C foundation tests, independent first review, fresh adversarial second review, PHP 8.1–8.4 exact-head CI |
| Requirement IDs | CF02-FR-008, 010, 013, 014, 015, 016, 017, 024, 025, 032 |
| Approval status | Implementation authorized; persistence, staging, live and runtime activation not approved |

No record in this register constitutes permission to activate CF-02. Runtime approval still requires measured extraction need, named staffing, real owner contracts, privacy/security review, schema/migration/rollback evidence, staging acceptance and explicit Founder approval.
