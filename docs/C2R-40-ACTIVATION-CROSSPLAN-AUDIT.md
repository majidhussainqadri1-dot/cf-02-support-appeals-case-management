# C2-R — Forty native-owner attestation checks, private-listing correctness, and cross-plan release ledger

## Governing sources
1. Sabri Social Homeopathy Platform Definitive Integrated Master Plan v3.0 (three consolidated governing plans): requirement → canonical owner → privacy/security → migration → test → rollback → evidence, with separate Specified, Source-Coded, Automated-QA Green, Staging-Accepted, Live-Deployed and Operational statuses.
2. CF-02 conditional master plan v1.0, Future24 amended 2026-09-08: 34 CF02-FR clauses, 24 gated Future24 foundations, native owner/source-of-truth separation, extraction gates and truthful staging/live evidence.
3. File 17 network/communications, File 18 marketplace, CF-01 clinical, CF-03 finance and File 00/02/09/19/20/24/25 native owners: CF-02 coordinates cases/appeals; no native decision or identity/ledger overrides.

## Exact source diagnosis and corrections

### C2R-01 — Fabricated File 00 activation health (confirmed source finding)
- Before: `WordPressActivationEvidence::dependencyReadiness` labeled File 00 `ready=true`, `healthy` and `health_checked_at=gmdate(DATE_ATOM)` **solely from** `function_exists('smc_membership_assertions')` plus `SMC_CONTRACT_VERSION`.
- Root cause: installation/version detection masqueraded as fresh provider health evidence, defeating the plan's actual-owner readiness distinction.
- Fix: default File 00 manifest remains `ready=false` like all mandatory dependencies. It can be marked ready **only** through explicit `cf02_dependency_contract_evidence` native-owner supplied manifest, checked by the canonical `RequiredCompanionContracts::validate`.
- Test: `tests/c2r-forty-activation-attestation.php` — exactly **40** bounded checks: 17 native/provider default manifests plus 23 owner-forgery/version/health/time/conditional-feature cases. A mock installed File 00 function and version constant must not bypass evidence.
- Operational impact: absent genuine File 00 attestation, activation intentionally remains dormant; no bypass or invented provider approval.

### C2R-02 — SQL failure returned as legitimate empty private listing (confirmed source finding)
- Before: `CompleteRestOverlay::appeals` and `::casePage` treated any non-array WordPress `$wpdb->get_results()` outcome as `[]`, so a database query failure could yield a successful 200 with zero cases/appeals.
- Root cause: missing distinction between valid empty array and database failure.
- Fix: new `AuthorizedQueryRows::fromDatabase` accepts real arrays, rejects `null`, `false` or malformed rows with `LogicException`. The overlay's generic failure response returns a bounded **503** instead of silently hiding outages; authorization filters and SQL owner scopes remain unchanged.
- Test: `tests/c2r-private-query-failures.php` exercises valid empty data, malformed values/rows, and wiring/error-status invariants.

## Existing forty-plan evidence: separate from C2-R
- `tests/crossplan-40-review.php`: 40 source-document companion/plan checks.
- `tests/c2o-plan-40-structural-witnesses.php`: 34 CF02-FR callable method pairs and six cross-plan source-method witnesses.
- `tests/c2q-40-native-outcome-contract.php`: 40 adversarial owner-outcome vectors against shared callback/worker validation.
- None proves 40 independent complete audit/fix cycles or 40 end-to-end live user journeys.

## Cross-file readiness audit
- File 00 identity: explicit owner-supplied fresh assertion and readiness; installer presence alone not evidence (C2R-01).
- File 17 message/report and File 19 notifications: native delivery and consent/retention proof pending.
- File 18 Marketplace: `main` at `04a5872e3e2e170ee400e7c607e39abd5484df65` was document-only; code candidate at separate Draft PR #9 `eb11cebd866205543e11888e824cd2a75609c631`. PR code does not establish merged source or live provider parity.
- CF-01/File 08 medical safety and CF-03 payments are conditional native owners and never support-owned write stores.
- Files 20/24/25 route, security/assurance and UI component contracts must be accepted by their true owners.

## Outstanding obstacles (explicitly unverified, not “fixed”)
1. Real File 00/02/09/17/18/19/20/21/24/25 manifests and storage/scanner/email/key providers, with owner signature/source and fresh health proof.
2. Provider HMAC exact key ↔ source-owner/method/route/purpose binding and valid producer compatibility test vectors. Avoid an unapproved breaking protocol change.
3. Live WordPress access, exact deployed source/package and checksum, active plugin version, DB columns/version, `wp_options` migration state and runtime logs. Previous WPVibe attempt to connect `sabrihomeopathy.com` returned Cloudflare HTTP 530, which does not prove a specific DNS/origin root cause.
4. Real MySQL errors and failover, database concurrency, replay, provider outages, fresh install/upgrade/migration/restore and rollback rehearsal.
5. Hostinger-equivalent staging, 40 real-role journeys where applicable, RTL/accessibility/security/performance checks, Founder exact-artifact approval, then deployment and live re-test.
6. Future24 is a guarded foundation, not accepted production completion of 24 distinct vendor/mobile/UI functions.

## Mandatory truth fields
- Repository main HEAD at review start: `bd52b96af28ce775a18860142aec84a62fb9b803`, unchanged.
- Corrective Draft PR #10 HEAD: inspect exact current head; do not reuse an earlier commit.
- Deployed Version: **unverified**.
- Live DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not verified**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Scope limit: repository-only checks and CI prove candidate behavior under fixtures, not parity, approved external owner acceptance or operational release.
