# CF-02 C2-V — Forty native-outcome transaction scenarios and cross-plan root-cause audit

**Review date:** 2026-10-21. **Repository:** `majidhussainqadri1-dot/cf-02-support-appeals-case-management`. **Plan authority:** CF-02 conditional master plan, Future24 amendment dated 2026-09-08; consolidated three central governing plans; communications File 17, marketplace File 18, CF-01 clinical, CF-03 finance, identity File 00 and notifications File 19 where applicable.

## Exact interpretation and evidence hierarchy
The 40 numbered reviews below are **40 distinct executable test scenarios** focused on native-owner case-result integrity, not 40 independent whole-project audit/fix rounds. The existing broader `tests/c2o-plan-40-structural-witnesses.php` checks all 34 CF02-FR and six central/cross-file requirements for source method pairs, while `tests/c2u-40-operational-safety-controls.php` checks 40 additional source safety invariants. These bounded code tests **do not verify** complete browser, provider, live WordPress or 24 Future24 mobile/UI workflows.

### Governing requirements tied to this session
- **CF02-FR-014:** SLA pause/resume must be deterministic, auditable and enforce version semantics.
- **CF02-FR-015:** SLA at-risk/breach classification and escalation must produce observable events, rather than silently changing hidden status.
- **CF02-FR-022:** CF-02 requests actions from the native owner, verifies their outcomes and does not overwrite companion native truth or prematurely claim completion.
- **CF02-FR-031 and 034:** legal hold, retention, secure deletion and immutable minimum evidence require fail-closed migration/database behavior.
- **Platform release law:** GitHub main, PR candidate, staging and installed live system are separate; complete source tests are not proof of deployed parity.

## Thirty-four plus six separate source-coverage baseline
`tests/c2o-plan-40-structural-witnesses.php` currently covers all `CF02-FR-001` to `CF02-FR-034` and six cross-plan witnesses: donor-neutral support, File 00/02 identity, schema migration, File 20 shell, emergency incident safeguards, and operational activation. `tests/c2u-40-operational-safety-controls.php` separately verifies 40 detailed security/owner invariants. Both suites succeeded in the exact-head CI at the tested candidate commit. They are structural evidence, not independent full lifecycle execution.

## Newly proven source-level defect categories and correction status

**C2V-01 — Split native outcome and SLA reconciliation.** Previously `recordCommandResultWithEvidence` committed the native result and event; then `RuntimeWorker` or `ProviderWebhookController` resumed a waiting-provider SLA outside that transaction. A failure could leave an authoritative terminal command while the SLA remained paused; retry could conflict with terminal immutability. Moved the SLA resume into the same repository-owned DB transaction, removed duplicate post-commit resume from normal worker/callback code, and let a valid *replayed* signed callback retry SLA resume for historically terminal records. Regression: `tests/c2v-40-atomic-native-outcomes.php`: **40/40** scenarios.

**C2V-02 — SLA breach state committed before its event.** Previously `RuntimeWorker::processSla` called `markSlaStatus` and `appendWorkerEvent` separately. If the event/audit write failed, the timer's updated status could suppress the next expected event. Added `OperationsRepository::markSlaStatusWithEvent` enforcing both in one transaction; worker uses this method, without duplicate event emission. Regression: `tests/c2v-sla-event-atomicity.php`: **12/12** scenarios, including failed BEGIN, SLA UPDATE, event INSERT, audit INSERT, and COMMIT.

**C2V-03 — Purge completion without matching final retention ledger.** Previously `RuntimeWorker::processRetention` invoked a committed `purgeCase` then separately wrote a `purged` retention ledger; a failed post-commit ledger could reclassify a deleted case as deferred. The `purged` ledger is now written within the same purge transaction after `SupportRetentionPurgeCompleted`, and the worker no longer attempts duplicate post-commit evidence. Raw `Throwable::getMessage()` was also removed from deferred error evidence to avoid leaking arbitrary private details. Regression: `tests/c2v-retention-ledger-atomicity.php`: **9/9** scenarios, including tombstone/child/delete/event/audit/final-ledger/COMMIT failure.

## Forty specific native-owner regression scenarios

| # | Native outcome | SLA initial state | Simulated failure | Required proof |
|---:|---|---|---|---|
| 1 | succeeded | waiting | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 2 | succeeded | other | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 3 | succeeded | running | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 4 | failed | waiting | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 5 | failed | other | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 6 | failed | running | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 7 | outcome_uncertain | waiting | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 8 | outcome_uncertain | other | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 9 | outcome_uncertain | running | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 10 | retry | waiting | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 11 | retry | other | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 12 | retry | running | none | Commit valid native outcome + event, restore SLA only for terminal waiting-provider |
| 13 | succeeded | waiting | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 14 | succeeded | other | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 15 | succeeded | running | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 16 | failed | waiting | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 17 | failed | other | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 18 | failed | running | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 19 | outcome_uncertain | waiting | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 20 | outcome_uncertain | other | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 21 | outcome_uncertain | running | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 22 | retry | waiting | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 23 | retry | other | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 24 | retry | running | sla | Rollback only when a terminal waiting-provider SLA update fails |
| 25 | succeeded | waiting | command | Rollback command and all local evidence on failed mutation |
| 26 | succeeded | waiting | event | Rollback command and all local evidence on failed mutation |
| 27 | succeeded | waiting | audit | Rollback command and all local evidence on failed mutation |
| 28 | succeeded | waiting | commit | Rollback command and all local evidence on failed mutation |
| 29 | failed | waiting | command | Rollback command and all local evidence on failed mutation |
| 30 | failed | waiting | event | Rollback command and all local evidence on failed mutation |
| 31 | failed | waiting | audit | Rollback command and all local evidence on failed mutation |
| 32 | failed | waiting | commit | Rollback command and all local evidence on failed mutation |
| 33 | outcome_uncertain | waiting | command | Rollback command and all local evidence on failed mutation |
| 34 | outcome_uncertain | waiting | event | Rollback command and all local evidence on failed mutation |
| 35 | outcome_uncertain | waiting | audit | Rollback command and all local evidence on failed mutation |
| 36 | outcome_uncertain | waiting | commit | Rollback command and all local evidence on failed mutation |
| 37 | retry | waiting | command | Rollback command and all local evidence on failed mutation |
| 38 | retry | waiting | event | Rollback command and all local evidence on failed mutation |
| 39 | retry | waiting | audit | Rollback command and all local evidence on failed mutation |
| 40 | retry | waiting | commit | Rollback command and all local evidence on failed mutation |

## Verified repository evidence before this documentation commit
- Tested candidate `27ad0b699f17b4e12d37f2ba89f3922db5bb9c00`.
- **40/40** C2-V native+SLA synthetic DB scenarios.
- **12/12** C2-V SLA-event rollback scenarios.
- **9/9** C2-V final retention ledger rollback scenarios.
- Existing 40/40 plan method witnesses and 40/40 operational safety, as well as prior signed metadata, privacy, version, guest-rate and retention suites, remained green.
- **Five/five** GitHub workflows successful (CF-02 CI, Active Runtime Integration, WordPress Lifecycle Smoke, Release Candidate and Exact Source Audit); PHP **8.1/8.2/8.3/8.4** CI successful.
- No migration/schema version change was required for these PHP transaction re-orderings. Confirmation of transaction rollback semantics on the actual MySQL/MariaDB storage engine is **still required**.

## Blocking items NOT removed and why
1. **Live Reality Freeze unavailable:** no verified live installed CF-02 plugin/version/source/ZIP checksum, WordPress configuration, database tables/columns/rows, `wp_options` migration state or runtime errors. A previous WordPress connector registration query had no connected site; connecting to `https://sabrihomeopathy.com` returned Cloudflare HTTP 530. HTTP 530 does **not** prove which hop or plugin is responsible.
2. **Cross-owner release parity:** File 00, 02, 09, 17, 18, 19, 20, 21, 24, 25 and optional/conditional File 26/CF-01/CF-03/CF-05 manifests and real credentials/providers must pass owner approval. File 18 Marketplace's exact accepted main and its separate Draft PR state must be reconciled before asserting a native source-of-truth integration. Do not fabricate attested manifests.
3. **Provider signing protocol:** real HMAC key/source owner/method/route/purpose binding and replay/rotation acceptance requires the native owner-approved, versioned producer-consumer protocol; an unilateral code change could break registered providers.
4. **Concurrency and transactional truth:** the fake-DB tests cannot prove actual engine/transaction boundaries, isolation under concurrent SLA updates, legal-hold placement during purge, provider replays, external side-effect compensation or transaction-deadlock recovery. A Hostinger-equivalent MySQL staging test must supply evidence.
5. **Other acceptance gates:** accessibility/Urdu RTL, mobile/low-bandwidth, independent security, load/soak, backups, restore/rollback, migrations, staff/on-call and Founder-approved exact artifact are outstanding. Future24 remains guarded foundations without blanket production acceptance.

## Counts and mandatory incident fields
- New independently proven source defect categories **this session: 3**; source fixes in Draft PR: **3**; exact-head synthetic CI: **green**; live defects proven fixed: **0**.
- Repository main HEAD at review start: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Draft PR #10 candidate HEAD: re-check exact SHA after this report commit.
- Deployed Version: **unverified**.
- DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not checked**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**No merge, no production deployment, no false Live-Resolved claim.**
