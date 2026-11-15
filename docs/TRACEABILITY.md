# CF-02 Central-Plan and File-Plan Requirements Traceability Matrix

## Status constitution

- **Coded-reviewed**: executable implementation plus regression evidence exists in repository source.
- **Runtime-integrated**: WordPress schema, REST/worker/route/surface wiring exists.
- **External evidence pending**: real companion/provider/staging/operations proof remains a separate gate.

## CF02-FR-001…034 baseline

| ID | Runtime evidence | Status |
|---|---|---|
| CF02-FR-001 | guided intake, dynamic safe fields, emergency/secret boundary, case persistence | Runtime-integrated |
| CF02-FR-002 | signed inbound adapters, sender trust, receipt ledger, exact replay | Runtime-integrated; real providers pending |
| CF02-FR-003 | immediate case receipt, SLA range, emergency boundary, File 19 outbox | Runtime-integrated |
| CF02-FR-004 | same-requester reversible merge/split, immutable events and redirects | Runtime-integrated |
| CF02-FR-005 | progressive rate/abuse controls and emergency-direction guardrail | Coded-reviewed |
| CF02-FR-006 | deterministic triage, priority, specialist/human paths and override evidence | Runtime-integrated |
| CF02-FR-007 | case workbench projection, thread, tasks, links, holds, audit | Runtime-integrated |
| CF02-FR-008 | one accountable owner, queue/skill/language routing, transfer/collaboration | Runtime-integrated |
| CF02-FR-009 | requester/agent communications, encrypted bodies, File 19 outbox | Runtime-integrated |
| CF02-FR-010 | internal/restricted notes, edit history, tasks and accidental-send separation | Runtime-integrated |
| CF02-FR-011 | quarantine, scan, verdict, redaction, expiring one-time delivery token | Runtime-integrated; real scanner/storage pending |
| CF02-FR-012 | governed resolution/closure/reopen, blockers and native outcome checks | Runtime-integrated |
| CF02-FR-013 | versioned SLA policy provider and configuration governance | Runtime-integrated |
| CF02-FR-014 | typed pause/resume evidence and persisted timer versions | Runtime-integrated |
| CF02-FR-015 | at-risk/breach worker, immutable events and escalation request | Runtime-integrated |
| CF02-FR-016 | privacy-safe backlog/SLA/reopen/quality metrics | Runtime-integrated |
| CF02-FR-017 | independent case-to-major-incident linkage and event | Runtime-integrated |
| CF02-FR-018 | standing, deadline, grounds, evidence and exception eligibility | Runtime-integrated |
| CF02-FR-019 | server-side conflict facts and independent reviewer assignment | Runtime-integrated; real owner facts pending |
| CF02-FR-020 | immutable dossier hash, policy/evidence/submissions and minimum access | Runtime-integrated |
| CF02-FR-021 | reasoned outcomes, findings, actions and further rights | Runtime-integrated |
| CF02-FR-022 | encrypted native commands, version expectation, retry/failure/uncertain/reconciliation | Runtime-integrated; real owners pending |
| CF02-FR-023 | accessible appeal routes, timeliness, representation and non-retaliation rules | Runtime-integrated |
| CF02-FR-024 | actor/capability/purpose/object/field/assignment/recent-auth/expiry checks | Runtime-integrated |
| CF02-FR-025 | secret detection, encrypted evidence, minimized projection, safe export/holds | Runtime-integrated |
| CF02-FR-026 | authorized bounded case search and hidden-count protection | Runtime-integrated |
| CF02-FR-027 | complete quality rubric, independent review, correction and privacy thresholds | Runtime-integrated |
| CF02-FR-028 | approved/versioned/expiring suggestion-only knowledge support | Coded-reviewed |
| CF02-FR-029 | optional feedback, opt-out, secret rejection and low-volume suppression | Runtime-integrated |
| CF02-FR-030 | stage/preview/dual approval/activate/rollback configuration | Runtime-integrated |
| CF02-FR-031 | bounded export status, reviewed holds and retention suspension | Runtime-integrated |
| CF02-FR-032 | durable event/outbox/command queues, retry/dead letter and consented fallback | Runtime-integrated |
| CF02-FR-033 | marked suggestion boundaries; no autonomous final/native/clinical/safety action | Coded-reviewed |
| CF02-FR-034 | approved schedule, hold-aware purge, provider reconciliation and evidence retention | Runtime-integrated |

## Latest rewritten CF-02 plan — complementary CEN requirements

| ID | Design / code / evidence | Test |
|---|---|---|
| CF02-CEN-01 | `ServiceEqualityPolicy`, `TriagePolicy::decideAt`, `AssignmentRouter`: severity/harm/deadline/competence routing; donor/popularity/ranking signals rejected | `tests/c2l-latest-two-plans.php`, Review 1 |
| CF02-CEN-02 | `OperationsRepository::linkObject`: canonical owner/type/ref/version/privacy class plus projection hash; no copied projection body persisted | C2-L trace test + existing runtime tests |
| CF02-CEN-03 | `AdverseDecisionNotice`: reason, policy/version, evidence summary, remedy, appeal route/deadline, immutable notice hash | C2-L implementation test |
| CF02-CEN-04 | `ReviewerProfile.organizationUnit` + `ReviewerAssignmentPolicy`: prior involvement, actor conflict and same-unit separation | C2-L + Review 1 |
| CF02-CEN-05 | `EmergencyRunbookRegistry`: distinct clinical red flag, imminent harm, account takeover, child safety, privacy breach and financial fraud boundaries; public-safe metadata only | C2-L + Review 1 |
| CF02-CEN-06 | `AccessContext` + `PurposeBoundAccessPolicy`: expiring assertion, case/queue assignment, purpose/field class, sensitive approval and recent-auth checks | existing C2-D suites + C2-L trace |
| CF02-CEN-07 | SLA timer/event runtime + explicit transition law + governed resolution; no silent state broadening | existing C2-C/C2-I suites + C2-L review |
| CF02-CEN-08 | `GuestIntakePolicy`, encrypted `GuestContinuationToken`, `GuestIntakeController`: low-sensitivity anonymous pre-intake only, authenticated step-up before case creation/sensitive disclosure | C2-L + Review 1 |
| CF02-CEN-09 | `OutcomeDeliveryGate` integrated into `ResolutionPolicy`: failed/dead-letter outcome delivery blocks false final resolution/auto-close | C2-L + Review 1 |
| CF02-CEN-10 | `SupportParityAudit`, `MonthlyParityAuditRunner`, calendar-month scheduler: aggregate-only privacy-thresholded donor/non-donor support parity; material variance emits release blocker | C2-L + Review 1 |

## Native CF-02 journeys

| ID | Repository evidence |
|---|---|
| CF02-NJ-01 | intake replay/dedupe → triage/SLA → assignment → messages → resolution/reopen |
| CF02-NJ-02 | account-safe support category → native identity command/reconciliation; ordinary ticket rejects secrets |
| CF02-NJ-03 | appeal eligibility → independent reviewer → dossier/decision → native owner implementation reconciliation |
| CF02-NJ-04 | privacy-specialist purpose-bound access, holds, export/retention and audit paths |
| CF02-NJ-05 | emergency classification/diversion; ordinary SLA/auto-close disabled for emergency runbook types |
| CF02-NJ-06 | durable outbox/commands, replay suppression, SLA correction, dead-letter visibility and recovery |

## Platform acceptance journeys consumed by CF-02

`AJ-09`, `AJ-10`, `AJ-18`, `AJ-20`, `AJ-24`, `AJ-25`, `AJ-34`, `AJ-35`, `AJ-36`, `AJ-38`, `AJ-39`, and `AJ-40` are integration acceptance gates. CF-02 provides its owned support/appeal/security/privacy/degraded-state boundaries, but a platform-wide pass requires the relevant native owners and staging evidence and is therefore **external evidence pending**, not fabricated by this repository.

## Central CV catalogue ownership

The CF-02 plan imports 56 Central CV requirements as owner/consumer obligations. The repository preserves native owner boundaries rather than copying those owners. C2-L closes the CF-02-owned gaps most directly associated with `CV-281` Support Center, `CV-280` two-review law, `CV-283` migration truth, `CV-284` vendor/dependency resilience, `CV-285` runbooks/on-call, and the shared security/privacy/accessibility/release requirements. Cross-repository CV journeys remain integration gates where CF-02 is a consumer rather than canonical owner.

## Central-plan invariants

| Invariant | Code evidence |
|---|---|
| One canonical owner | native-owner allowlist; commands only; no companion table writes |
| File 00 identity authority | actor-bound assertion factory; no WordPress-role inference as canonical authority |
| File 20 shell owner | route contracts/shortcodes; no second global shell |
| File 19 transport owner | durable outbox requests; no transport truth claim; delivery failure does not become false resolution |
| File 24 assurance boundary | dependency evidence; native CF-02 controls remain enforceable |
| Public/private law | public help and safe guest pre-intake; authenticated sensitive actions; private no-store/noindex routes |
| Security/privacy | encryption, signatures, replay controls, strong versions, purpose access and minimization |
| Free-core/donor parity | privilege signal rejection plus monthly aggregate parity audit |
| Two fresh reviews | C2-L implementation + Review 1 + fresh adversarial Review 2 are permanent regression gates |
| Truthful completion | staging/live/operational remain separate external statuses |

## Truthful candidate status

- Runtime candidate: `1.0.0-rc.7`.
- Schema: `1.3.0`; contract: `1.3.0`; plan: `1.0`.
- Automated status must be taken only from exact-head workflow evidence after the final corrective commit.
- Hostinger staging, real companion/provider contracts, browser/device/accessibility, independent security, load/soak, migration rehearsal, restore/rollback, staffing, observation and Founder exact-artifact acceptance remain separate gates.

## V12.3 C2-N corrective trace (2026-11-15)

The definitive V12.3 CF-02 register is **74 ownership/acceptance items**: 34 FR + 10 CEN + 6 NJ + 24 conditional FUT. V12.3 does not convert feature-gated FUT domain foundations into accepted user-facing functionality.

| V12.3 item | New source evidence | Regression | Still external |
|---|---|---|---|
| `CF02-FR-001`, `CF02-CEN-08` | Public `guestPreIntake()`, category allowlist, encrypted token and authenticated `/guest/continue` in `cf02-guest.js` | `c2n-v12-3-alignment.php`, `c2n-review1.php` | File 00 real session, guest navigation and browser/multi-device behavior |
| `CF02-CEN-05`, `CF02-NJ-05` | Guest 422 emergency/specialist diversion displayed without false case receipt | `c2n-review2.php` | Specialist contacts, emergency runbooks and observational testing |
| `CF02-FR-007/026/030` | CSP-friendly File 00-scoped `CompleteAdminSurfaces`, external `cf02-admin.js/css`, no template `innerHTML` | `c2n-review2.php`, JavaScript syntax | True staff capabilities, host Content Security Policy and admin browser tests |
| `F25-CEN-01/02`, `CEN-BRAND-001` | File 25 green `#087A4E`, hover `#065C3B`, responsive logical CSS and adaptive dark palette | `c2n-v12-3-alignment.php` | Screenshots, contrast, RTL/LTR, assistive technology, cross-device tests |
| `CF02-FR-009/023` | PO/POT/MO Urdu strings for guest, notices and operator dashboard | `c2n-review2.php` | WordPress locale/browser and actual notices across providers |
| `CEN-REL-001` | rc.8 exact manifest, source, docs and CI alignment; preserved external gates | `tests/release.php`, CI, package workflow | Deployed checksum parity, staging, rollback and founder acceptance |

**Current status:** code corrections are review candidates; the exact deployed source, live database, schema/migration version and runtime defects remain unverified. No live fix is claimed.
