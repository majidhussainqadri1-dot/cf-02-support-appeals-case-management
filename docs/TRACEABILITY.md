# CF-02 Central-Plan and File-Plan Requirements Traceability Matrix

## Status constitution

- **Coded-reviewed**: executable implementation plus C2-I foundation, forty-round corrective suite, C2-K harmonization and two C2-L release-governance reviews.
- **Runtime-integrated**: WordPress schema, REST command/query, worker, route or surface code exists.
- **External evidence pending**: real provider/companion/staging/operations proof remains separate.

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

## Central-plan invariants

| Invariant | Code evidence |
|---|---|
| One canonical owner | native-owner allowlist; commands only; no companion table writes |
| File 00 identity authority | exact actor-bound assertion factory; no WP-role inference |
| File 20 shell owner | route contracts and shortcodes; no second global shell |
| File 19 transport owner | durable outbox requests; no transport truth claim |
| File 24 assurance boundary | dependency evidence; native controls remain in CF-02 |
| Public/private law | public help, authenticated actions, private no-store/noindex routes |
| Security/privacy | encryption, signatures, replay, strong ETag, purpose access, minimization |
| Forty corrective review/fix rounds | `docs/FORTY-ROUND-REVIEW-REGISTER.md`, `tests/c2j-forty-review.php` |
| Truthful completion | release gate still requires external evidence |

## Truthful implementation status

- Source state: corrective `1.0.0-rc.6`, schema `1.3.0`.
- Automated status: must be taken from the exact-head GitHub workflows; local passes alone are not staging acceptance.
- External state: real companion/provider, Hostinger staging, browser/accessibility, independent security, load, restore, rollback, observation and Founder exact-artifact approval remain pending.

## All-Chats v2.1 directive traceability

| Directive | Runtime evidence | Status |
|---|---|---|
| `CHAT-BIZ-022` — single free tier and donor non-privilege | `learning_access` taxonomy, legacy alias, `ServiceEqualityPolicy`, REST boundary rejection, C2-K parity tests | Coded-reviewed |
| `CHAT-GOV-023` — Islamic institutional governance and due process | specialist governance category, `InstitutionalDueProcessPolicy`, native implementation verification and appeal safeguards | Coded-reviewed; native institutional owner integration pending |
| `CHAT-QA-001` — post-GitHub harmonization and iterative review | C2-K change record, Review 1, fresh adversarial Review 2, full regression/CI/package gates | Coded-reviewed; platform-wide final harmonization remains future cross-repository gate |
| Doctor ranking complaint/appeal boundary | conditional File 26 contract and `PrivacySafeOutcomeProjection`; no appeal-use or donor signal | Coded-reviewed; real File 26 contract pending |
| Green, RTL, icon and accessibility rule | external CSS/JS, platform green token fallback, logical properties, SVG icons, 44px controls, keyboard/reduced-motion/forced-colors states | Coded-reviewed; real browser/device acceptance pending |

## Machine-verifiable evidence

`release/traceability.json` contains the exact ordered CF02-FR-001 through CF02-FR-034 set plus central-plan and All-Chats directives. `build/verify_traceability.py` fails CI if any evidence path is absent, any ID is missing/duplicated, or external staging evidence is represented as passed.

## C2-M fresh forty-round corrective trace

- Test: `tests/c2m-forty-fresh-review.php`
- Register: `docs/FORTY-ROUND-FRESH-REVIEW-C2M.md`
- Change control: `docs/CHANGE-CONTROL-C2M.md`
- Provider contract: `docs/PROVIDER-SIGNATURE-CONTRACT.md`
- Requirements strengthened: CF02-FR-001 intake; CF02-FR-002 signed provider intake; CF02-FR-006 triage; CF02-FR-010 internal-note visibility; CF02-FR-011 secure attachments; CF02-FR-018 appeal eligibility; CF02-FR-024 purpose-bound access; CF02-FR-029 feedback booleans; CF02-FR-030 governed repair; CF02-FR-032 degraded/provider channels.
- Evidence boundary: code and automated regression only; external staging evidence remains pending.
