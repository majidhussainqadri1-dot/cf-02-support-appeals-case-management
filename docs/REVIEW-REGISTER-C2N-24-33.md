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
- Make the domain attachment state machine mirror the runtime transition law.
- Add R24 regression assertions and run exact-head PHP lint, full Composer test suite, and security scan before R25.

Status: **findings frozen; correction follows this completed review.**
