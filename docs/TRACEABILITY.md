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
| CF02-FR-008 | C2-A/C | Foundation-partial | queues, skills and staffing roles; assignment/capacity/transfer service pending |
| CF02-FR-009 | C2-B | Foundation-partial | requester message model and safe portal projection; templates/transports pending |
| CF02-FR-010 | C2-B | Foundation-partial | internal/restricted visibility and accidental-send prevention; mentions/tasks persistence pending |
| CF02-FR-011 | C2-B | Foundation-partial | quarantine, structured scan evidence, rejection, redaction, C4 vault and case-bound delivery law; real scanner/storage/purge pending |
| CF02-FR-012 | C2-B | Foundation-partial | governed resolution, native outcome reference, blocker checks, closure notice and reopen window; persisted notices and verification pending |
| CF02-FR-013 | C2-C | Specified | SLA policy registry not coded |
| CF02-FR-014 | C2-C | Specified | pause/resume law not coded |
| CF02-FR-015 | C2-C | Specified | breach prediction/escalation not coded |
| CF02-FR-016 | C2-C | Foundation-partial | queue definitions only; queue-health metrics not coded |
| CF02-FR-017 | C2-C | Specified | major-incident linkage not coded |
| CF02-FR-018 | C2-E | Specified | appeal eligibility not coded |
| CF02-FR-019 | C2-A/E | Foundation-partial | staffing conflict rules; reviewer assignment pending |
| CF02-FR-020 | C2-E | Specified | appeal dossier not coded |
| CF02-FR-021 | C2-E | Specified | reasoned appeal decision not coded |
| CF02-FR-022 | C2-D/E | Foundation-partial | companion-manifest law and resolution references; native command/reconciliation service pending |
| CF02-FR-023 | C2-E | Specified | appeal deadlines/representatives/accessibility pending |
| CF02-FR-024 | C2-A/B/D | Foundation-partial | C2–C4 classes, specialist queues, thread minimization, case-bound attachments; runtime access engine pending |
| CF02-FR-025 | C2-B/D | Foundation-partial | secret detection, redaction state and restricted evidence; complete DLP/export chain pending |
| CF02-FR-026 | C2-C/D | Specified | authorized case search not coded |
| CF02-FR-027 | C2-F | Specified | quality review not coded |
| CF02-FR-028 | C2-F | Specified | knowledge suggestions not coded |
| CF02-FR-029 | C2-F | Specified | satisfaction workflow not coded |
| CF02-FR-030 | C2-A/F | Foundation-partial | taxonomy, queues, change-control and validators; staged configuration service pending |
| CF02-FR-031 | C2-B/D | Specified | export/legal hold not coded |
| CF02-FR-032 | C2-A/B/C | Foundation-partial | dormant fail-closed runtime and queued receipt state; real outage/recovery adapters pending |
| CF02-FR-033 | C2-F | Specified | automation engine not coded |
| CF02-FR-034 | C2-G/H | Specified | retention/deletion jobs not coded |

## Remaining release evidence

Real owner contracts, WordPress schema/migrations, mounted routes, providers, scanner/storage, authorization, browser/accessibility/load tests, backup/restore, rollback rehearsal, staging acceptance, Founder runtime approval and zero unresolved defects remain mandatory.
