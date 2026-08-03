# CF-02 Requirements Traceability Matrix

## Status constitution

- **Coded-reviewed**: governed implementation and automated tests exist, including two review/fix suites for the owning phase.
- **Runtime-coded**: WordPress schema, route, scheduler or surface code exists, but real staging execution remains unproved.
- **External evidence pending**: completion depends on a companion module, provider, staffing, migration dataset, browser/device, backup environment or Founder acceptance.

| ID | Phase | Code status | Primary evidence and remaining external boundary |
|---|---|---|---|
| CF02-FR-001 | C2-B/H | Runtime-coded | Guided intake models, category fields, REST intake, transactional replay ledger and accessible support form; staging/browser UAT pending |
| CF02-FR-002 | C2-B/F/H | Coded-reviewed | Channel taxonomy, canonical idempotency, intake/message/outbox replay control; real inbound email/chat signatures pending |
| CF02-FR-003 | C2-B/F | Coded-reviewed | `CaseReceipt`, `ReceiptFactory`, safe outbox and retry/dead-letter lifecycle; File 19 delivery contract acceptance pending |
| CF02-FR-004 | C2-B/G | Coded-reviewed | Same-requester merge preview, immutable source identity, reversal reason and migration mapping; production dedupe dataset pending |
| CF02-FR-005 | C2-F | Coded-reviewed | `ProgressiveRateLimiter` implements soft challenge, hard bounded penalty, authenticated/guest limits and emergency-direction preservation |
| CF02-FR-006 | C2-A/B | Coded-reviewed | Versioned taxonomy, deterministic triage, sender trust, specialist/human/emergency outcomes and fail-closed validation |
| CF02-FR-007 | C2-B/H | Runtime-coded | Versioned case workspace plus canonical WordPress cases/messages/attachments/tasks schema and requester APIs; staging persistence UAT pending |
| CF02-FR-008 | C2-A/C/H | Coded-reviewed | Skill/capacity/language/clearance routing, expiring decisions, one owner, transfer/collaboration law and assignment persistence schema |
| CF02-FR-009 | C2-B/F/H | Runtime-coded | Requester thread, safe portal projection, encrypted REST message intake, outbox/templates and accessible surface; transport UAT pending |
| CF02-FR-010 | C2-B/C/H | Coded-reviewed | Requester/internal/restricted separation, accidental-send controls, scoped collaborators and governed `CaseTask` lifecycle |
| CF02-FR-011 | C2-B/H | Coded-reviewed | Quarantine, structured scanner evidence, hash/MIME verdict, redaction, C4 delivery law and attachment schema; real scanner/private storage pending |
| CF02-FR-012 | C2-B/F | Coded-reviewed | Resolution codes, native outcome, blocker/reconciliation checks, notice, closure/reopen window and automation no-autoclose boundary |
| CF02-FR-013 | C2-C/F | Coded-reviewed | Versioned SLA registry plus governed staged configuration/approval/rollback service |
| CF02-FR-014 | C2-C/H | Coded-reviewed | Timezone/holiday calendar, typed unique evidence, pause/resume law, anti-backdating and SLA persistence schema |
| CF02-FR-015 | C2-C/H | Coded-reviewed | Breach prediction, human escalation, load budget and bounded scheduler hooks; production telemetry pending |
| CF02-FR-016 | C2-C/H | Runtime-coded | Minimized queue-health snapshot, freshness/capacity validation and admin operations shell; live metrics pipeline pending |
| CF02-FR-017 | C2-C | Coded-reviewed | Service-bound major incident linkage with independent cases, chronology and public-minimized projection; File 24/incident-command acceptance pending |
| CF02-FR-018 | C2-E | Coded-reviewed | Standing, timing, grounds, evidence and documented late-exception eligibility policy |
| CF02-FR-019 | C2-A/E | Coded-reviewed | Independent reviewer competence, prior-involvement, conflict, availability and sensitive-clearance assignment |
| CF02-FR-020 | C2-E/H | Coded-reviewed | Immutable original-decision dossier, append-only submissions, integrity hash and dossier persistence schema |
| CF02-FR-021 | C2-E | Coded-reviewed | Reasoned uphold/modify/overturn/remand/withdraw decision, findings, evidence, actions and further rights |
| CF02-FR-022 | C2-D/E/H | Coded-reviewed | Native-owner command aggregate, replay guard, retry/dead-letter/compensation and implementation reconciliation; real owner adapters pending |
| CF02-FR-023 | C2-E/H | Runtime-coded | Deadline rules, accessibility/guardian exceptions, non-retaliation findings and accessible appeal surface; policy/legal UAT pending |
| CF02-FR-024 | C2-D/H | Coded-reviewed | `AccessContext` and `PurposeBoundAccessPolicy` enforce capability, assignment, purpose, classification, approval, recent auth and expiry |
| CF02-FR-025 | C2-B/D/H | Coded-reviewed | Secret detection, encrypted message storage, restricted evidence, bounded export, manifest hashes, legal holds and safe audit context |
| CF02-FR-026 | C2-D | Coded-reviewed | Authorized queue/case search over minimized projections, bounded cursor, safe filters and hidden-result non-disclosure |
| CF02-FR-027 | C2-F/H | Coded-reviewed | Independent quality rubric, random/risk sampling basis, identity suppression, agent correction appeal and case-fix tracking |
| CF02-FR-028 | C2-F | Coded-reviewed | Approved/versioned/source-bound knowledge suggestions, expiry and suggestion-only execution boundary |
| CF02-FR-029 | C2-F/H | Coded-reviewed | Optional/opt-out feedback, secret rejection and low-volume cohort suppression with persistence schema |
| CF02-FR-030 | C2-A/F/H | Coded-reviewed | Versioned configuration snapshots, validation, preview/stage, dual approval, activation and rollback history |
| CF02-FR-031 | C2-D/F/H | Coded-reviewed | Purpose-bound bounded export, authenticated manifest, scoped reviewed legal holds and retention suspension |
| CF02-FR-032 | C2-A/F/H | Coded-reviewed | Fail-closed activation, durable outbox, exponential retry, dead letter, consented alternate channel and bounded workers |
| CF02-FR-033 | C2-F | Coded-reviewed | Marked classification/summarization/draft suggestions; final appeal, identity, refund, clinical, safety and native mutations prohibited |
| CF02-FR-034 | C2-F/G/H | Coded-reviewed | Category retention, shorter attachment period, hold-aware purge planner, provider/cache/index purge instruction and retention ledger |

## Complete automated matrix

The workflow executes PHP 8.1, 8.2, 8.3 and 8.4 with strict Composer validation, syntax checks, all C2-A/B/C foundation suites and C2-D/E/F/G/H foundation plus first-review and fresh adversarial second-review suites.

## Truthful release boundary

The repository now contains the complete governed coding candidate for the plan. This does **not** prove real companion adapters, scanner/object storage, email/notification providers, staffing/calendar sources, WordPress staging persistence, browser/accessibility/device behavior, production dataset migration, load/soak results, backup/restore, rollback rehearsal, package parity, observation window or Founder exact-version acceptance. The `ReleaseGate` keeps runtime acceptance blocked until every item is evidenced.
