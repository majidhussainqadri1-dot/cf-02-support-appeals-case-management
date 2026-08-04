# CF-02 Forty-Round Review and Defect-Correction Register

Governing sources: Definitive Master Plan v3.0 and CF-02 v1.0. Every round is Review → Fix → Retest. A round may close with no defect only when its inspected scope and evidence are recorded.

| Round | Review domain | Mandatory evidence | Status |
|---:|---|---|---|
| 01 | Exact-artifact activation binding | unit + runtime activation | FIXED: plugin-bootstrap constant handling |
| 02 | File 00 fail-closed identity assertion | negative actor/audience/expiry tests | OPEN |
| 03 | No duplicate WordPress role authority | role/capability scan | OPEN |
| 04 | Guardian/representative scope | object-scope tests | OPEN |
| 05 | Suspension and recent-auth revalidation | action-time tests | OPEN |
| 06 | Case lifecycle law | transition/property tests | OPEN |
| 07 | Assignment and transfer integrity | concurrency tests | OPEN |
| 08 | SLA pause/resume/breach law | clock tests | OPEN |
| 09 | Queue purpose separation | authorization tests | OPEN |
| 10 | Intake validation and emergency diversion | negative journey tests | OPEN |
| 11 | Idempotency and replay | changed-payload tests | OPEN |
| 12 | Deduplication merge/split reversibility | provenance tests | OPEN |
| 13 | User-visible/private thread separation | projection tests | OPEN |
| 14 | Internal-note confidentiality | leakage tests | OPEN |
| 15 | Attachment quarantine/scanning | lifecycle tests | OPEN |
| 16 | Redaction/supersession/download grants | replay/expiry tests | OPEN |
| 17 | Appeal eligibility and deadlines | boundary tests | OPEN |
| 18 | Reviewer independence/conflicts | separation tests | OPEN |
| 19 | Appeal dossier immutability | hash/version tests | OPEN |
| 20 | Native-decision command boundary | no-direct-write scan | OPEN |
| 21 | Implementation reconciliation | retry/drift tests | OPEN |
| 22 | File 19 delivery boundary | outbox/retry tests | OPEN |
| 23 | File 17 linked-conversation boundary | contract tests | OPEN |
| 24 | File 24 privacy/security boundary | ownership scan | OPEN |
| 25 | CF-03 financial boundary | no-ledger-write scan | OPEN |
| 26 | Cursor pagination and hidden counts | pagination tests | OPEN |
| 27 | Search field/purpose authorization | IDOR tests | OPEN |
| 28 | Managed encryption keys | rotation/retired-key tests | OPEN |
| 29 | Webhook signing and replay | stale/replay tests | OPEN |
| 30 | Retention/legal holds/purge | fail-closed tests | OPEN |
| 31 | Audit tamper evidence | chain verification | OPEN |
| 32 | Migration, dual-read and rollback | reconciliation tests | OPEN |
| 33 | Repair safety/idempotency | repair tests | OPEN |
| 34 | Scheduler/deactivation/uninstall | lifecycle smoke | OPEN |
| 35 | Public/user portal completeness | journey matrix | OPEN |
| 36 | Admin operations completeness | capability matrix | OPEN |
| 37 | Accessibility/RTL/reduced motion | automated + manual checklist | OPEN |
| 38 | Performance/load/degraded providers | budgets/outage tests | OPEN |
| 39 | Package/SBOM/provenance parity | deterministic build | OPEN |
| 40 | Fresh adversarial whole-system audit | exact-head full CI | OPEN |

No round may be marked complete merely because code exists or a prior CI run was green. All defects discovered in a round must be corrected before that round closes.
