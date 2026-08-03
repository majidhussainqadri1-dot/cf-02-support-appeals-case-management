# CF-02 Requirements Traceability Matrix

Status terms:

- **Specified**: requirement exists in the governing plan.
- **Foundation-partial**: reviewed domain/policy code exists, but persistence, integrations or end-to-end runtime acceptance remain.
- **Not coded**: no implementation claim.

| ID | Phase | Status | Current evidence / remaining boundary |
|---|---|---|---|
| CF02-FR-001 | C2-B | Foundation-partial | `IntakeRequest`, dynamic category fields, impact/urgency/language/accessibility/consent validation; route and persistence pending |
| CF02-FR-002 | C2-B | Foundation-partial | channel enum, canonical idempotency and replay ledger; web/email/system adapters and signatures pending |
| CF02-FR-003 | C2-B | Foundation-partial | `CaseReceipt` and `ReceiptFactory`; File 19/provider delivery pending |
| CF02-FR-004 | C2-B/G | Foundation-partial | same-requester merge, immutable preview, reversal reason; persisted merge/split ledger pending |
| CF02-FR-005 | C2-B | Specified | progressive rate, bot/spam and blocked-abuser runtime controls not coded |
| CF02-FR-006 | C2-A/B | Foundation-partial | taxonomy and deterministic triage, sender trust, specialist/human/emergency flags; human override service pending |
| CF02-FR-007 | C2-B | Foundation-partial | versioned `CaseWorkspace`, messages, attachments, blockers and state; database/workbench pending |
| CF02-FR-008 | C2-A/C | Foundation-partial | `AgentProfile`, `AssignmentRequest`, deterministic router, short-lived decisions, one accountable owner, scoped collaboration, transfer history and concurrency; staffing-source synchronization and persistence pending |
| CF02-FR-009 | C2-B | Foundation-partial | requester message model and safe portal projection; templates/transports pending |
| CF02-FR-010 | C2-B/C | Foundation-partial | internal/restricted visibility, accidental-send prevention and scoped expiring collaborators; mentions/tasks persistence pending |
| CF02-FR-011 | C2-B | Foundation-partial | quarantine, structured scan evidence, rejection, redaction, C4 vault and case-bound delivery law; real scanner/storage/purge pending |
| CF02-FR-012 | C2-B | Foundation-partial | governed resolution, native outcome reference, blocker checks, closure notice and reopen window; persisted notices and verification pending |
| CF02-FR-013 | C2-C | Foundation-partial | versioned `SlaPolicy` and queue/priority registry with first-response/update/resolution targets; configuration persistence and approval workflow pending |
| CF02-FR-014 | C2-C | Foundation-partial | timezone/holiday-aware `CoverageCalendar`, typed unique evidence, governed pause/resume and anti-backdating law; scheduler/persistence pending |
| CF02-FR-015 | C2-C | Foundation-partial | `BreachPredictor`, watch/high/breach evidence and human-governed escalation policy; production telemetry and workers pending |
| CF02-FR-016 | C2-C | Foundation-partial | minimized `QueueHealthSnapshot`, freshness checks and healthy/watch/critical evaluator; dashboard and metric pipeline pending |
| CF02-FR-017 | C2-C | Foundation-partial | versioned `MajorIncident`, service-bound case links, chronology, public minimized projection and independent-case law; incident-command integration pending |
| CF02-FR-018 | C2-E | Specified | appeal eligibility not coded |
| CF02-FR-019 | C2-A/E | Foundation-partial | staffing conflict rules and restricted assignment boundaries; reviewer assignment pending |
| CF02-FR-020 | C2-E | Specified | appeal dossier not coded |
| CF02-FR-021 | C2-E | Specified | reasoned appeal decision not coded |
| CF02-FR-022 | C2-D/E | Foundation-partial | companion-manifest law and resolution references; native command/reconciliation service pending |
| CF02-FR-023 | C2-E | Specified | appeal deadlines/representatives/accessibility pending |
| CF02-FR-024 | C2-A/B/C/D | Foundation-partial | C2–C4 classes, specialist queues, thread minimization, case-bound attachments, cleared sensitive assignment and purpose-bound restricted collaboration; runtime access engine pending |
| CF02-FR-025 | C2-B/C/D | Foundation-partial | secret detection, redaction state, restricted evidence and incident-public-text checks; complete DLP/export chain pending |
| CF02-FR-026 | C2-C/D | Specified | authorized case search not coded |
| CF02-FR-027 | C2-F | Specified | quality review not coded |
| CF02-FR-028 | C2-F | Specified | knowledge suggestions not coded |
| CF02-FR-029 | C2-F | Specified | satisfaction workflow not coded |
| CF02-FR-030 | C2-A/F | Foundation-partial | taxonomy, queues, change-control and validators; staged configuration service pending |
| CF02-FR-031 | C2-B/D | Specified | export/legal hold not coded |
| CF02-FR-032 | C2-A/B/C | Foundation-partial | dormant fail-closed runtime, queued receipt state, explicit unassigned result, stale-metric failure and approved coverage escalation; real outage/recovery adapters pending |
| CF02-FR-033 | C2-F | Specified | automation engine not coded |
| CF02-FR-034 | C2-G/H | Specified | retention/deletion jobs not coded |

## C2-C exact-head evidence

GitHub Actions run `30844984327` passed on head `4267d5bb407a651a65208d175265e3818e3334ae` for PHP 8.1, 8.2, 8.3 and 8.4. The matrix included syntax, all C2-A/C2-B suites, the C2-C foundation suite, first-review regressions and fresh adversarial second-review tests.

## Remaining release evidence

Real owner contracts, WordPress schema/migrations, mounted routes, transaction boundaries, scheduler workers, staffing/calendar/notification providers, scanner/storage, runtime authorization, dashboards, browser/accessibility/load tests, backup/restore, rollback rehearsal, staging acceptance, Founder runtime approval and zero unresolved defects remain mandatory.
