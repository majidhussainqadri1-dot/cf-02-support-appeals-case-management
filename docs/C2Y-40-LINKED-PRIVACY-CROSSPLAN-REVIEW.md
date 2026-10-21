# CF-02 C2-Y: Forty privacy-class and atomic event reviews

**Review date:** 2026-10-21; **module:** CF-02 Support, Appeals and Case Management; **original user sources:** CF-02 conditional complete master plan (Future24 amended 2026-09-08), consolidated three central governing plans, File 17 Communications, File 18 Marketplace, CF-01 Clinical, CF-05 Analytics, File 00 identity and File 19 outbound delivery.

## Governing source distinctions
CF02-FR-024: purpose-bound, case/assignment/field-scoped access; transfer/closure must revoke access. CF02-FR-025: private redaction, evidence handling and safe exports. The CF-02 data and commands sections require **canonical native owner, typed references, data class and tamper-evident atomic audit**; partial writes must not be reported successful. The plans also prohibit duplicate canonical owner stores, unauthorized visibility, and implicit live acceptance from repository CI.

## Proven source defects at previous corrective SHA `e4b3c67f8c29487fa09aad878e6d5c7957949ba5`
**One central linked-object consistency defect, with two confirmed subdefects.**
1. `OperationsRepository::linkObject()` compared `projection_hash` and `object_version`, but **not** `privacy_class`, when deciding whether an existing link needed an update. A privacy-class-only change was ignored.
2. Both new/updated linked object rows were committed outside a transaction, **before** `SupportDomainObjectLinked` and the audit row were written. If event/audit insert failed, the record could be mutated while evidence was unavailable.

## C2-Y correction
- `src/Infrastructure/WordPress/OperationsRepository.php`: atomic DB transaction around the row lock (`SELECT ... FOR UPDATE`), privacy/version/projection equality comparison, insert or update, `SupportDomainObjectLinked` event and tamper-evident audit.
- `privacy_class` now appears in the event payload (safe classification **metadata only**, not the attached clinical/message contents), and is checked whenever a linked-object change is requested.
- Any row, event or audit failure throws before commit; no invalid success or unaudited mutation is allowed.
- No schema migration was introduced for the source change. Nevertheless, **existing older event payloads did not include `privacy_class`**: replay under the *same* event Idempotency Key may now collide. This is a deliberate bounded fail-closed change, **not** proof that an in-flight production replay can safely cross the cutover. A staged compatibility/replay drain decision is mandatory.

## 40 newly executed test scenarios
`tests/c2y-40-linked-privacy-atomicity.php`, wired into Composer and CI, exercises **2 storage states × 4 data classifications × 5 failure modes = 40 distinct scenarios**. Existing C1 with unchanged version/projection is also a no-op replay check. A fake WordPress DB simulates snapshot and rollback; the runner additionally verifies privacy classification in the emitted event and required transaction/row-lock source wiring.

| # | Link state | Privacy class | Failure injected | Expected state |
|---:|---|---|---|---|
| 01 | new | C1 | none | Persist privacy + event/audit in one commit |
| 02 | new | C1 | begin | Reject: no partial writes |
| 03 | new | C1 | write | Reject write error; existing same-class no-op remains valid |
| 04 | new | C1 | event | Rollback write on missing event |
| 05 | new | C1 | audit | Rollback write/event on missing audit |
| 06 | new | C2 | none | Persist privacy + event/audit in one commit |
| 07 | new | C2 | begin | Reject: no partial writes |
| 08 | new | C2 | write | Reject write error; existing same-class no-op remains valid |
| 09 | new | C2 | event | Rollback write on missing event |
| 10 | new | C2 | audit | Rollback write/event on missing audit |
| 11 | new | C3 | none | Persist privacy + event/audit in one commit |
| 12 | new | C3 | begin | Reject: no partial writes |
| 13 | new | C3 | write | Reject write error; existing same-class no-op remains valid |
| 14 | new | C3 | event | Rollback write on missing event |
| 15 | new | C3 | audit | Rollback write/event on missing audit |
| 16 | new | C4 | none | Persist privacy + event/audit in one commit |
| 17 | new | C4 | begin | Reject: no partial writes |
| 18 | new | C4 | write | Reject write error; existing same-class no-op remains valid |
| 19 | new | C4 | event | Rollback write on missing event |
| 20 | new | C4 | audit | Rollback write/event on missing audit |
| 21 | existing | C1 | none | Persist privacy + event/audit in one commit |
| 22 | existing | C1 | begin | Reject: no partial writes |
| 23 | existing | C1 | write | Reject write error; existing same-class no-op remains valid |
| 24 | existing | C1 | event | Rollback write on missing event |
| 25 | existing | C1 | audit | Rollback write/event on missing audit |
| 26 | existing | C2 | none | Persist privacy + event/audit in one commit |
| 27 | existing | C2 | begin | Reject: no partial writes |
| 28 | existing | C2 | write | Reject write error; existing same-class no-op remains valid |
| 29 | existing | C2 | event | Rollback write on missing event |
| 30 | existing | C2 | audit | Rollback write/event on missing audit |
| 31 | existing | C3 | none | Persist privacy + event/audit in one commit |
| 32 | existing | C3 | begin | Reject: no partial writes |
| 33 | existing | C3 | write | Reject write error; existing same-class no-op remains valid |
| 34 | existing | C3 | event | Rollback write on missing event |
| 35 | existing | C3 | audit | Rollback write/event on missing audit |
| 36 | existing | C4 | none | Persist privacy + event/audit in one commit |
| 37 | existing | C4 | begin | Reject: no partial writes |
| 38 | existing | C4 | write | Reject write error; existing same-class no-op remains valid |
| 39 | existing | C4 | event | Rollback write on missing event |
| 40 | existing | C4 | audit | Rollback write/event on missing audit |

### Verified repository status before this documentation-only commit
- Source commit `da6a0542068706502034702b148875027fc79410`: **40/40 C2-Y cases successful**, verified from actual GitHub CI log.
- **5/5 workflows succeeded**: CI, Active Runtime Integration, WordPress Lifecycle Smoke, Release Candidate and Exact Source Audit. PHP 8.1/8.2/8.3/8.4 all succeeded.
- Existing `tests/c2o-plan-40-structural-witnesses.php` **40/40** (34 CF02-FR plus six central/cross-file code method witnesses) and `tests/c2u-40-operational-safety-controls.php` **40/40** remain green.
- These 40 are **bounded transaction unit scenarios**, not 40 independently repeated complete repository forensic audits or 40 production provider tests. Real MySQL/MariaDB concurrence and operational role/owner permissions remain an outstanding test.

## Barriers and owners: what CI does NOT prove
1. **Privacy/classification owner attestation still missing.** The native owner must verify data classification and object/version/visibility. CF-02 currently receives caller-provided `privacy_class`; an authenticated requester or staff member's label is not a signed canonical owner assertion. A verified owner-controlled classification protocol should be agreed and staged before activating sensitive linked-object features. No invented provider token or unapproved breaking protocol patch was introduced.
2. **Live Reality Freeze** is blocked: WPVibe has no connected WordPress sites; `sabrihomeopathy.com` returned Cloudflare HTTP 530 again. This is connection evidence, not proof of DNS/origin or plugin defect. Active CF-02 version, package SHA, exact deployed code, WordPress runtime, database version/schema/rows/`wp_options`, migrations and server logs cannot be established.
3. **File 18 main/source parity**: `majidhussainqadri1-dot/18-sabri-marketplace` main `04a5872e3e2e170ee400e7c607e39abd5484df65` is separate from **14 open PRs**. A PR or CI artifact is not the deployed native owner.
4. **Companion provider acceptance** for File 00/02/09/17/18/19/20/21/24/25, conditionally CF-01/CF-03/File 26/CF-05, private storage/malware scanning, keys, message delivery, signed callbacks and sender/purpose binding is still absent.
5. **Production readiness**: Hostinger-equivalent staging DB and concurrency/rollbacks, privacy-class transition authorization, migration/replay compatibility, backup/restore, independent security/RTL/accessibility/mobile tests, provider outages, load/soak, staffed operations and Founder exact-artifact approval remain pending.
6. **Future24** foundations do not prove completed delivery of 24 native UI/mobile/provider functions; each has its own staging/legal/security change gate.

## Exact defect and state ledger
- Newly proven **source defect categories this session: 1**, with **two subdefects**.
- Source-level corrected/tested: **1 category (both subdefects)**.
- Known production fixes independently re-tested: **0**.
- Repository main HEAD at review start: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Draft PR #10 HEAD: re-evaluate after this report commit.
- Deployed Version: **unverified**.
- DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not verified**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**No merge, no production deployment, no live-Resolved claim.**
