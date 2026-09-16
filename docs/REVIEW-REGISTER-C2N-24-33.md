# CF-02 Fresh Review Register — R24–R33

This register follows the governing sequence: **complete review → freeze all findings → correct all proven defects → exact-head regression/QA → next review**. No coding correction is started during a review sweep.

## R24 — State-machine and domain/runtime consistency

**Review completed before correction.** Scope: governing CF-02 appeal/case/attachment state paths, `RuntimeWorkflowPolicy`, domain state machines/enums, REST transition surface, and persisted runtime identifiers.

### Frozen findings

1. **R24-01 — Case state duplicate truth drift.** Runtime uses `waiting_user` / `waiting_provider` and supports governed `withdrawn` / reopen flow, while the domain `CaseState`/`CaseStateMachine` uses `waiting_for_user` / `waiting_for_provider` and omits `withdrawn`. This permits domain-level validation/tests to disagree with the actual WordPress runtime state law.
2. **R24-02 — Appeal native-decision bypass.** Runtime policy allows `under_review → decided` directly even though the governing CF-02 state path and the domain appeal aggregate require `under_review → native_decision_pending → decided`. This can bypass the explicit native-owner decision-pending gate.
3. **R24-03 — Attachment state duplicate truth drift.** Domain and runtime attachment transition maps differ (`scanned → redacted`, rejected expiry, superseded purge), leaving two contradictory state laws.

### Frozen correction plan

- Make domain case values/transitions mirror the runtime/persisted identifiers, including governed withdrawal/reopen paths.
- Remove direct `under_review → decided`; decisions must traverse `native_decision_pending`.
- Make the attachment domain/runtime state law identical and preserve the governed scanned-to-redacted operational path.
- Add R24 regression assertions and run exact-head PHP lint, full Composer test suite, and security scan before R25.

Status: **corrected and validated before R25.**

## R25 — Authorization, IDOR, sensitive-data and scoped-access boundaries

**Review completed before correction.** Scope: REST query/mutation authorization, principal context, capability checks, case/appeal object-level authorization, queue assignment, representative access, C4/C5 step-up, attachment-token issuance, quality/audit access, and the purpose-bound authorization model.

### Frozen findings

1. **R25-01 — Unassigned/cross-assigned appeal object access.** `appealForActor()` accepts `appeal.review` or `appeal.decision` as sufficient staff access. The reviewer mismatch restriction applies only when the caller has `appeal.review`, the appeal already has a reviewer, and the caller lacks queue-read. Consequently a reviewer can read an unassigned appeal by identifier, and a decision-capable actor can read another reviewer's appeal by identifier without queue-wide authority. This is an object-scope/IDOR boundary defect against independent, assigned review.
2. **R25-02 — Audit sample capability becomes global arbitrary-case read.** `caseForActor()` treats `audit.sample.read` exactly like `queue.manage`, bypassing active assignment for every case identifier. The quality/sample capability is therefore not bounded to an actually selected audit sample and can become blanket case access, contrary to JIT/scoped support access.

### Frozen correction plan

- Require non-queue appeal staff to be the already assigned reviewer for object reads; unassigned appeals remain visible only through the governed queue surface to queue-authorized actors.
- Remove `audit.sample.read` as a generic `caseForActor()` bypass. Sampling/quality endpoints may return bounded sample projections, but arbitrary case lookup must still require requester/representative, assignment/specialist scope, or queue-management authority.
- Add negative regression assertions for both boundaries and run exact-head full QA before R26.

Status: **findings frozen; correction follows this completed review.**
