# CF-02 C2-U — Forty New Operational-Safety Review Controls

**Date:** 2026-10-21; **Module:** CF-02 Support, Appeals & Case Management.
**Source governance:** CF-02 conditional complete master plan (34 CF02-FR requirements + separately gated 24 Future24 foundations); three consolidated governing central plans; File 00/02 identity/authentication, File 17 communications, File 18 marketplace, File 19 delivery, File 20 shell, File 24 assurance, File 25 components, conditional CF-01/CF-03/CF-05 owners.

## Exact review scope, no inflated completion
This C2-U pass performs **40 separate source-safety guard checks** `tests/c2u-40-operational-safety-controls.php`, not a re-run of the prior 40 implementation method pairs. They confirm presence, wiring and ordering of selected safety policies, **not** 40 independent full-project cycles, external interoperability or live execution.

| ID | Verified safety requirement | Plan / native owner linkage | Evidence grade |
|---:|---|---|---|
| 01 | Exact companion registry: 17 distinct owners/providers | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 02 | Exactly 14 mandatory activation contracts | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 03 | Exactly 3 conditional-only owner contracts | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 04 | File 18 remains marketplace decision owner | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 05 | File 17 remains communication/report owner | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 06 | File 19 remains native notification owner | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 07 | File 00 function presence never generates readiness | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 08 | Native owner default manifest is unready | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 09 | Owner health unknown is not healthy | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 10 | Exact deployed SHA is not fabricated by default | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 11 | Owner contract requires fresh health timestamp | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 12 | Future-dated provider health has hard ceiling | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 13 | Source owner supplies explicit manifest by hook | Activation / File 00, 02, 17–20, 24–25 | Passed: exact source invariant |
| 14 | Emergency diversion precedes intake throttling | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 15 | Case creation after guest continuation requires capability | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 16 | Guest pre-intake declares case not created | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 17 | Guest throttling acquires MySQL named lock | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 18 | Absent named lock never yields allowance | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 19 | Named lock released in finally path | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 20 | Guest throttling requires durable transient set | CF02-FR-001, 005, 006 / File 00 | Passed: exact source invariant |
| 21 | Signed provider request bounded to 300 seconds | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 22 | Signed provider request body limited to 65536 bytes | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 23 | Incoming HMAC signs timestamp + raw body | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 24 | Inbound replay compares exact payload fingerprint | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 25 | Sender and provider metadata validated before use | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 26 | Inbound body forbids known prohibited secrets | CF02-FR-002, 009 / File 17, 19 | Passed: exact source invariant |
| 27 | Native webhook uses single validated result policy | CF02-FR-022 / Native owner | Passed: exact source invariant |
| 28 | Native background worker uses same result policy | CF02-FR-022 / Native owner | Passed: exact source invariant |
| 29 | Native version cannot clamp on integer overflow | CF02-FR-022 / Native owner | Passed: exact source invariant |
| 30 | Successful native result requires owner proof | CF02-FR-022 / Native owner | Passed: exact source invariant |
| 31 | Event published mark checks exact durable update | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 32 | Event retry mark checks exact durable update | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 33 | Published worker event is announced only after status persistence | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 34 | SQL list outage throws non-input LogicException | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 35 | Private authorized-query outage has HTTP 503 path | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 36 | Invalid API query has separate HTTP 400 path | CF02-FR-026, 032 / Event/Query | Passed: exact source invariant |
| 37 | Repair requires scoped approved and recently authenticated actor | CF02-FR-030 / Founder approval | Passed: exact source invariant |
| 38 | Unknown legal hold state refuses destructive purge | CF02-FR-031, 034 / Retention | Passed: exact source invariant |
| 39 | Retention child deletions use checked DML helpers | CF02-FR-031, 034 / Retention | Passed: exact source invariant |
| 40 | Purge completion evidence is within the deletion transaction | CF02-FR-034 / Audit and retention | Passed: exact source invariant |

## Two newly proven C2-T code defects resolved on this Draft PR

**C2T-01 — Wrong HTTP classification for genuine database unavailability.**
`OperationsRepository::rows()` was updated in C2-S to throw `RuntimeException` on SQL failure/malformed rows, while `CompleteRestOverlay::preDispatch()` catches `RuntimeException` as a user query error **HTTP 400**. This treated server/DB outages as user mistakes. Reclassified the shared helper's genuine database failure to `LogicException`, routed by the existing `catch(Throwable)` to safe HTTP **503**. Existing C2-S fake-DB tests were updated to distinguish this class intentionally.

**C2T-02 — Undurable event delivery marked successfully published.**
`OperationsRepository::markEventPublished()` and `::markEventRetry()` discarded `$wpdb->update()` results. Thus `RuntimeWorker::processEvents()` could count/announce successful publication without persisted event state. Both methods now require an affected-row result of exactly 1; otherwise they fail closed. Worker runs through a retry path instead of a false success; real provider idempotency and high-concurrency correctness still need staged verification.

**New regression:** `tests/c2t-event-status-and-query-classification.php`, **17/17 synthetic negative/positive tests** passed.
**New second-order review:** `tests/c2u-40-operational-safety-controls.php`, **40/40 focused invariants** passed.
Existing separate 40/40 CF02-FR/central callable witnesses, 40/40 crossplan document checks, 40/40 activation, 40/40 native-outcome, 35/35 provider metadata and 28/28 retention checks continue under CI.

## Source/CI evidence (initial exact-head before this documentation-only commit)
- `35f717b9c88747d50dfc814b13b532fc7e6cbfcc` — **5/5 workflows successful** including release candidate, source audit, WordPress lifecycle smoke and active runtime integration.
- CF-02 CI run `39559356364`; PHP **8.1, 8.2, 8.3, 8.4 successful**; recorded C2-U 40/40 and C2-T 17/17 at PHP 8.2.
- Repository main `bd52b96af28ce775a18860142aec84a62fb9b803` remains unchanged; work lives in **Draft PR #10**, no merge or deploy.

## What is still blocked
1. **Live Reality Freeze:** no independently verified installed plugin/source/package hash, WordPress runtime/config, DB/schema rows/indexes, `wp_options` migrations or logs. Prior WordPress connector reported no registered site; connecting via Cloudflare returned HTTP 530; that symptom does not identify the origin problem.
2. **Cross-owner contract parity:** File 18 marketplace source was historically on separate unmerged PR rather than main. Actual canonical owner File 00/02/09/17/18/19/20/21/24/25 and conditional CF-01/CF-03/File 26 manifests, provider health and signed integration events require staged acceptance. No CF-02 self-attestation may replace this.
3. **Provider HMAC protocol:** exact signer key ↔ claimed source owner, method, route and purpose binding plus real producer test vectors require approved versioned change control. No silent breaking change.
4. **Database/high concurrency:** MySQL transactions, outbox at-least-once dedupe, worker races, legal-hold concurrent placement, provider retries, failed updates, migration/retention/rollback and backup restore must run on Hostinger-equivalent staging. No claim of tested operational atomicity.
5. **Security/QA:** authenticated real-role journeys, external security/accessibility/Urdu RTL/mobile tests, provider outage/low-bandwidth, monitoring/on-call, performance/soak and Future24 integration gates outstanding.
6. **Approval:** exact package SHA/checksum, independent review, Founder acceptance, staged release, monitored live retest before any production-Resolved claim.

## Defect ledger and truth fields
- **New proven source defect categories in C2-T/C2-U:** 2.
- **Fixed in Draft repository source:** 2, with CI tests successful.
- **New critical/high defects independently verified absent in live:** not established.
- **All remaining coding proven complete:** no; only tested scoped controls and prior FR source witnesses.
- **Repository main HEAD:** `bd52b96af28ce775a18860142aec84a62fb9b803`.
- **Draft PR HEAD:** re-query exact latest SHA after this document commit.
- **Deployed Version:** unverified.
- **DB Version:** unverified.
- **Migration State:** unverified.
- **Live Verification Status:** not performed.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
