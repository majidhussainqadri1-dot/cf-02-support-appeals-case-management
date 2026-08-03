# CF-02 Change-Control Register

## CF02-CCR-0001 — Controlled repository foundation

C2-A established fail-closed activation, owner contracts, taxonomy, queues, staffing, configuration validation and traceability. Implementation was authorized; runtime activation was not.

## CF02-CCR-0002 — Intake and case-work foundation

C2-B established guided intake, replay control, case/thread/attachment aggregates, reversible deduplication, resolution/closure/reopen and minimized user projections. Implementation was authorized; persistence and staging were deferred.

## CF02-CCR-0003 — Assignment, SLA and incident foundation

C2-C established one accountable owner, skill/capacity/language routing, scoped collaboration, versioned calendars/SLA clocks, breach prediction, escalation, queue health and independent-case incident linkage. Two review/fix rounds and PHP 8.1–8.4 CI passed.

## CF02-CCR-0004 — Complete C2-D through C2-H coding candidate

| Field | Value |
|---|---|
| Requested by | Founder instruction to complete all remaining coding in one continuous pass, with two review/fix rounds after every part |
| Recorded at | 2026-08-04T00:36:00+05:00 |
| Affected scope | C2-D authorization/commands/search/evidence; C2-E appeals; C2-F quality/knowledge/feedback/configuration/automation/degraded delivery/retention; C2-G migration; C2-H WordPress schema/runtime/resilience/release |
| Old rule | C2-A through C2-C were coded; C2-D through C2-H and WordPress persistence/runtime were not implemented |
| New rule | Repository contains a complete governed `1.0.0-rc.1` coding candidate, kept fail-closed until all external release evidence passes |
| Canonical ownership | CF-02 owns support cases, case threads, assignments, SLA, appeal dossier/orchestration, quality and reconciliation; native modules retain identity, moderation, listing, payment, privacy incident and clinical decisions |
| Data impact | Adds idempotent WordPress schema v1.1.0 for cases, messages, attachments, assignments, SLA, appeals/dossiers, commands, outbox, tasks, quality, feedback, holds, configuration, migration, retention and audit |
| Security/privacy impact | Purpose-bound access, recent authentication, encryption, replay control, progressive rate limits, safe search/export, legal holds, no-store/noindex, secret detection and tamper-evident audit |
| Appeal/fairness impact | Standing/deadline/exception policy, independent reviewer conflicts, immutable dossier, reasoned decision, further rights, native implementation confirmation and non-retaliation |
| Automation impact | Suggestions/drafts only; autonomous appeal, identity, refund, clinical, safety and native mutation remain prohibited |
| Migration plan | Shadow/dual read, immutable mapping, source/target hashes, strict parity and zero unexplained case/SLA/appeal divergence before cutover |
| Rollback plan | Development branch can be reverted; runtime activation remains blocked. Production rollback requires staged database/package rehearsal and deletion-ledger reapplication |
| Review plan | Two independent review/fix suites for each C2-D, C2-E, C2-F, C2-G and C2-H part, plus full cross-phase PHP 8.1–8.4 regression matrix |
| Requirement IDs | CF02-FR-001 through CF02-FR-034 |
| Approval status | Complete coding candidate authorized; merge, packaging, staging acceptance, live activation and operational acceptance not yet approved |

## Mandatory release boundary

This register does not authorize activation merely because code and repository QA are complete. `ReleaseGate` must remain blocked until real companion contracts, providers, scanner/storage, staffing/calendar sources, WordPress staging, accessibility/browser/device tests, migration rehearsal, load/soak, backup/restore, rollback, package parity, observation window, zero unresolved critical/high defects and Founder approval are evidenced for the exact commit and package.
