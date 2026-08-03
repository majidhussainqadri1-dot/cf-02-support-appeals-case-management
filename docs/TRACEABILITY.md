# CF-02 Requirements Traceability Matrix

Status terms:

- **Specified**: requirement is present in the governing plan and register.
- **Foundation-partial**: a non-runtime policy/validation component exists; end-to-end behavior does not yet exist.
- **Not coded**: no implementation claim.

| ID | Phase | Current status | Current evidence / next implementation |
|---|---|---|---|
| CF02-FR-001 | C2-B | Specified | Guided intake schema and user route not coded |
| CF02-FR-002 | C2-B | Specified | Web/email/form/event adapters not coded |
| CF02-FR-003 | C2-B | Specified | Receipt, case ID and expectation service not coded |
| CF02-FR-004 | C2-B/G | Specified | Deduplication and reversible merge not coded |
| CF02-FR-005 | C2-B | Specified | Spam/rate/abuse controls not coded |
| CF02-FR-006 | C2-A/B | Foundation-partial | `SupportTaxonomy`; triage engine remains C2-B |
| CF02-FR-007 | C2-B | Specified | Case workspace not coded |
| CF02-FR-008 | C2-A/C | Foundation-partial | `QueueRegistry`, `SkillCatalog`, staffing roles; assignment service remains C2-C |
| CF02-FR-009 | C2-B | Specified | User-visible thread not coded |
| CF02-FR-010 | C2-B | Specified | Internal notes and mentions not coded |
| CF02-FR-011 | C2-B | Specified | Attachment scanning/redaction/storage not coded |
| CF02-FR-012 | C2-B | Foundation-partial | Case state machine exists; outcome verification and closure notices not coded |
| CF02-FR-013 | C2-C | Specified | SLA policy registry not coded |
| CF02-FR-014 | C2-C | Specified | Pause/resume law not coded |
| CF02-FR-015 | C2-C | Specified | Breach prediction/escalation not coded |
| CF02-FR-016 | C2-C | Foundation-partial | Queue definitions exist; queue-health metrics not coded |
| CF02-FR-017 | C2-C | Specified | Major-incident linkage not coded |
| CF02-FR-018 | C2-E | Specified | Appeal eligibility not coded |
| CF02-FR-019 | C2-A/E | Foundation-partial | `StaffingRegistry` conflict rules; reviewer assignment service not coded |
| CF02-FR-020 | C2-E | Specified | Immutable appeal dossier not coded |
| CF02-FR-021 | C2-E | Specified | Reasoned decision service not coded |
| CF02-FR-022 | C2-D/E | Foundation-partial | Companion manifest law exists; native action/reconciliation service not coded |
| CF02-FR-023 | C2-E | Specified | Deadlines, representatives and accessible appeal journey not coded |
| CF02-FR-024 | C2-A/D | Foundation-partial | C2/C3/C4 categories, restricted queue and staffing prohibitions; access engine not coded |
| CF02-FR-025 | C2-B/D | Specified | Redaction and secure evidence pipeline not coded |
| CF02-FR-026 | C2-C/D | Specified | Authorized case search not coded |
| CF02-FR-027 | C2-F | Specified | Quality review sampling not coded |
| CF02-FR-028 | C2-F | Specified | Knowledge suggestions not coded |
| CF02-FR-029 | C2-F | Specified | Optional satisfaction workflow not coded |
| CF02-FR-030 | C2-A/F | Foundation-partial | Taxonomy, queues, skills, change-control validator and rollback law; staged config service not coded |
| CF02-FR-031 | C2-B/D | Specified | Case export/legal hold not coded |
| CF02-FR-032 | C2-B/C | Foundation-partial | Fail-closed activation exists; channel-specific degraded processing not coded |
| CF02-FR-033 | C2-F | Specified | Automation boundaries documented; automation engine not coded |
| CF02-FR-034 | C2-G/H | Specified | Retention/deletion data lifecycle not coded |

## C2-A exit evidence still required

- validated structured activation records from real owners;
- measured volume and complexity trigger;
- named queue owners, coverage hours and escalation tree;
- approved privacy/security reviews and restricted-evidence matrix;
- migration, reconciliation and rollback rehearsal evidence;
- all companion contracts available at supported versions;
- zero known unresolved Critical/High defects;
- two completed review-and-correction rounds on the exact head.
