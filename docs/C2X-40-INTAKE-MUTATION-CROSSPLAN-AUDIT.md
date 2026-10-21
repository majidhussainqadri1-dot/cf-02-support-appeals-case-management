# CF-02 C2-X: Fresh forty cross-plan reviews and actual WordPress intake mutation tests

**Date:** 2026-10-21. **Scope:** CF-02 Support, Appeals & Case Management against the CF-02 governing plan amended 2026-09-08, consolidated three central plans, File 17 Communications, File 18 Marketplace, File 19 Notification ownership, File 00/02 identity, File 20/25 shell/visual, CF-01 clinical and conditional analytics boundaries. Source-only claims must not be called staging/live evidence.

## Forty traceable requirement/control checks

The pre-existing `tests/c2o-plan-40-structural-witnesses.php` verifies that the 34 CF02-FR obligations plus six central/cross-file requirements still have two callable source-method witnesses and traceability rows. It remains green. **These are 40 bounded checks, not forty complete independent forensic code audits or proof that the entire module is finished.**

| # | ID | Plan scope | Evidence limitation |
|---:|---|---|---|
| 1 | CF02-FR-001 | Guided intake | Source-method witness under C2-O; external acceptance separate; new full-mutation intake guard applies |
| 2 | CF02-FR-002 | Omnichannel adapters | Source-method witness under C2-O; external acceptance separate |
| 3 | CF02-FR-003 | Receipt and expectations | Source-method witness under C2-O; external acceptance separate |
| 4 | CF02-FR-004 | Deduplication and merge | Source-method witness under C2-O; external acceptance separate; new full-mutation intake guard applies |
| 5 | CF02-FR-005 | Spam/abuse and rate safety | Source-method witness under C2-O; external acceptance separate |
| 6 | CF02-FR-006 | Triage policy | Source-method witness under C2-O; external acceptance separate |
| 7 | CF02-FR-007 | Case workspace | Source-method witness under C2-O; external acceptance separate |
| 8 | CF02-FR-008 | Assignment and collaboration | Source-method witness under C2-O; external acceptance separate |
| 9 | CF02-FR-009 | User communication | Source-method witness under C2-O; external acceptance separate; new full-mutation intake guard applies |
| 10 | CF02-FR-010 | Internal notes and mentions | Source-method witness under C2-O; external acceptance separate |
| 11 | CF02-FR-011 | Attachment handling | Source-method witness under C2-O; external acceptance separate |
| 12 | CF02-FR-012 | Resolution and closure | Source-method witness under C2-O; external acceptance separate |
| 13 | CF02-FR-013 | SLA policy registry | Source-method witness under C2-O; external acceptance separate |
| 14 | CF02-FR-014 | Pause/resume law | Source-method witness under C2-O; external acceptance separate |
| 15 | CF02-FR-015 | Breach prediction and escalation | Source-method witness under C2-O; external acceptance separate |
| 16 | CF02-FR-016 | Queue health | Source-method witness under C2-O; external acceptance separate |
| 17 | CF02-FR-017 | Major incident linkage | Source-method witness under C2-O; external acceptance separate |
| 18 | CF02-FR-018 | Appeal eligibility | Source-method witness under C2-O; external acceptance separate |
| 19 | CF02-FR-019 | Independent reviewer | Source-method witness under C2-O; external acceptance separate |
| 20 | CF02-FR-020 | Appeal dossier | Source-method witness under C2-O; external acceptance separate |
| 21 | CF02-FR-021 | Reasoned decision | Source-method witness under C2-O; external acceptance separate |
| 22 | CF02-FR-022 | Implementation reconciliation | Source-method witness under C2-O; external acceptance separate |
| 23 | CF02-FR-023 | Appeal timeliness and access | Source-method witness under C2-O; external acceptance separate |
| 24 | CF02-FR-024 | Purpose-bound access | Source-method witness under C2-O; external acceptance separate; new full-mutation intake guard applies |
| 25 | CF02-FR-025 | Redaction and secure evidence | Source-method witness under C2-O; external acceptance separate |
| 26 | CF02-FR-026 | Case search | Source-method witness under C2-O; external acceptance separate |
| 27 | CF02-FR-027 | Quality review | Source-method witness under C2-O; external acceptance separate |
| 28 | CF02-FR-028 | Knowledge suggestions | Source-method witness under C2-O; external acceptance separate |
| 29 | CF02-FR-029 | Satisfaction and feedback | Source-method witness under C2-O; external acceptance separate |
| 30 | CF02-FR-030 | Configuration governance | Source-method witness under C2-O; external acceptance separate |
| 31 | CF02-FR-031 | Case export and legal hold | Source-method witness under C2-O; external acceptance separate |
| 32 | CF02-FR-032 | Degraded channels | Source-method witness under C2-O; external acceptance separate |
| 33 | CF02-FR-033 | Automation boundaries | Source-method witness under C2-O; external acceptance separate |
| 34 | CF02-FR-034 | Retention and deletion | Source-method witness under C2-O; external acceptance separate |
| 35 | CEN-OWN / File 17/18/19 | Native ownership and message/marketplace/notification boundaries | Source-owner boundary / live parity not inferred |
| 36 | File 00/02 assertions | Canonical identity, actor/capability/purpose authority | Source-owner boundary / live parity not inferred |
| 37 | CV-283 | Exact schema/migration truth and database change control | Source-owner boundary / live parity not inferred |
| 38 | File 20/25 | Single shell and RTL/accessibility component owner | Source-owner boundary / live parity not inferred |
| 39 | CV-285/CEN-SAFE | Emergency diversion and independent review safeguards | Source-owner boundary / live parity not inferred |
| 40 | CEN-REL | Two reviews, exact source-package parity, staging and live re-test | Source-owner boundary / live parity not inferred |

## New proven source-level defects and corrective code

### C2X-01: Incomplete intake idempotency fingerprint (CF02-FR-001/002/004/009; File 17/18)

**Source bug:** `ComprehensiveRestController::createCase` sent an intake replay payload to `IntakeRepository::createOrReplay` without its message `description` or the `affected_object` metadata. Yet those inputs mutate `messages` and `case_links` after the case replay result. An Idempotency Key reused with a different description/linked object could be accepted as an exact replay and cause divergent downstream effects or misleading success. Owner references must not be made into CF-02 native records.

**Fix:** Add a hash of the normalized description (`description_sha256`) and a canonical sorted fingerprint of the normalized native object (`affected_object_sha256`) to the replay envelope. Only hashes contribute to the `intake_replay.payload_hash` ledger, not private description or projected content. Same request must be idempotent; altered description/native object must fail closed before further mutations.

### C2X-02: Invalid native object validated after canonical case creation (CF02-FR-001/024, CV-283)

**Source bug:** The old code read and checked `affected_object` only after the intake repository committed the new case. An unsupported owner, missing ID/version or malformed privacy class could reject an API request **after** a case had already been created.

**Fix:** Normalize/check any supplied native-owner reference, version, type, privacy classification, safe projection type and bounded reference lengths **before** calling `createOrReplay`. Reject incomplete metadata rather than silently skipping it; link only after a validated envelope. This closes a proven late-validation path; it does not claim arbitrary failures in downstream post-create message/link/outbox steps are now transactionally compensated.

## Actual WordPress+MariaDB integration evidence

- Expanded `tests/wordpress-runtime-smoke.php`: **forty distinct same-key adversarial replays** (20 altered descriptions including missing description, 20 new native-object references) must all be rejected.
- The original exact replay with unchanged message remains successful, crossing a deliberate two-second delay introduced in C2-W.
- Missing native object `ref` is rejected before any new case is created. After negative tests the real CI database must contain exactly **one case, one requester message, one receipt outbox entry and zero linked native objects** from these fixtures.
- Five workflows and PHP 8.1–8.4 succeeded at tested code SHA `1f14992c9b83ab98acb2db10212b12c6fe5d5f0e`.
- These are controlled ephemeral WordPress + MariaDB runner results; **Hostinger staging/production is unverified**.

## Mandatory deployment and migration caveat

Adding new binding fields changes the canonical request fingerprint even when older active intake records were written without them. **Exact replay of an in-flight legacy 24-hour idempotency window across the code cutover may correctly reject as collision**. No deployed code or old ledger state has been inspected. Production-safe cutover requires a timed drain/supported compatibility decision, measured ledger counts, staged upgrade tests, operations notice, Founder change-control, backed-up rollback and no silent hash fallback that would re-enable mutation mismatch. GitHub draft must not merge/deploy without this gate.

## Remaining external blockers and owners

1. **Live Reality Freeze:** Exact installed plugin version/files/ZIP checksum, WordPress config, DB/schema/rows/columns/indexes/`wp_options`/migration and logs unavailable. WPVibe reports no site registered; `sabrihomeopathy.com` again returned Cloudflare HTTP 530 without an origin diagnosis. Do not change Cloudflare/WAF to guess.
2. **Companion owner contracts:** File 17 message context, File 19 delivery ownership, File 18 marketplace canonical records (main vs 14 open PRs as observed), and File 00/02/09/20/21/24/25 and conditional CF-01/03/05/File 26 manifests need actual provider signatures, version/health and sandbox owner consent.
3. **Integration/storage:** Native webhook signing key ↔ route/method/purpose contract, object-storage malware scanner, encryption keys, email sender and actual message callback compatibility not staged accepted.
4. **Real concurrency/retention:** Concurrent request/hold-vs-purge, database transaction/storage-engine semantics, external provider partial side effects and recovery/rollback/restore not established by the 40 scenario loop.
5. **Release gate:** Urdu RTL/accessibility, security, mobile, load/soak, operator coverage, approved exact source-installable-ZIP checksum, Founder signed approval, staging acceptance and monitored live retest remain pending.
6. **Future24:** conditional coded foundations do not prove the 24 final user-facing operations live.

## Defect counts, source and live incident state
- New confirmed source defect categories **this pass: 2**; corrected in Draft source and CI: **2**. Actual live-resolved defects: **0**.
- Repo `main` baseline: `bd52b96af28ce775a18860142aec84a62fb9b803`; candidate corrective branch SHA will advance on this doc commit.
- **Deployed Version:** unverified. **DB Version:** unverified. **Migration State:** unverified. **Live Verification Status:** not performed.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
