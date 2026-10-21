# CF-02 C2-P — Follow-up adversarial source review and four corrective categories

## Governing sources and evidence type
- Central: Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan 2026.
- Module: CF-02 Support, Appeals & Case Management Complete Conditional Plan, Future24 amended 2026-09-08.
- Cross-owner: File 17 communication/report; File 18 marketplace; CF-01 clinical records; CF-03 finance; mandatory identity File 00/02/09, delivery File 19, shell File 20, assurance File 24, UI File 25.
- Earlier 40 source/domain review subjects: `docs/C2N-40-SOURCE-REVIEW-REGISTER.md`.
- Earlier 40 plan/implementation witnesses: `docs/C2O-40-PLAN-CODE-WITNESSES.md`, enforced on CI by `tests/c2o-plan-40-structural-witnesses.php`.
- This follow-up contains **targeted deeper security reviews**. It is NOT evidence of 40 independent whole-repository review/fix cycles. All FR/Future requirement acceptance is still evaluated per implementation and external gate.

## C2-P newly proven and corrected source risks

| ID | Plan requirement | Proven source defect | Source fix | Executable proof |
|---|---|---|---|---|
| C2P-01 | CF02-FR-022 / trusted owner reconciliation | `native_version` was cast to int without checking type/canonical decimal/max range; oversized or malformed signed fields could become a different integer | `Integration/NativeResultVersion.php` and `ProviderWebhookController.php` reject unsupported values | `tests/c2p-native-result-security.php` |
| C2P-02 | CF02-FR-002 / signed provider load safety | Callback HMAC processed unbounded HTTP body | Explicit 65536-byte signed body ceiling before digest | Same executable suite (boundary/overflow/valid HMAC/forgery/time-window cases) |
| C2P-03 | CF02-FR-024/025 and central privacy law | Provider, REST overlay, core controller and runtime boot diagnostic hooks passed Throwable instances directly to other listeners; sensitive details could propagate into extensions/telemetry | Removed raw error objects; emit bounded trace ID and error class | C2-P suite plus complete `src` PHP exception-object source scanner |
| C2P-04 | CF02-FR-001/004/009/022/032 / atomic effects | OperationsRepository and IntakeRepository ignored `START TRANSACTION` / `COMMIT` failure results and could report unverified success | Fail closed on failed transaction BEGIN or COMMIT; rollback/error propagated | `tests/c2p-transaction-failclosed.php` including begin/commit errors and callback rollback |

Additional negative observation: the newly introduced complete source scanner initially found one more raw exception-object transmission in `src/Plugin.php`. This was corrected in the same branch and final scan on **161 PHP source files found zero of the defined raw exception reporting patterns**. Scanner success is not a proof that every conceivable telemetry privacy leak has been excluded.

## Exact CI verification (SHA before this report-only commit)
- Exact examined commit `59c83c317476f5d77e1ce826abbabd7eb3d1088c`: GitHub **5/5** named workflows succeeded.
- CI passed PHP **8.1, 8.2, 8.3 and 8.4**.
- 40/40 structural CF02-FR + cross-plan witnesses; 53/53 C2-P version/signature/privacy tests; 6/6 transaction tests; 161 source PHP files scanned with 0 matching raw exception patterns.
- Separate existing 40 source/document consistency and 35 inbound-provider metadata tests remain required in full Composer/CI.
- Release artifact is a repository **candidate**; a GitHub workflow cannot verify Hostinger live installed plugin or DB.

## Still unverified — compulsory release blockers
1. Signature trust still needs a provider-approved, exact **key/source-owner/method/route/purpose** binding protocol plus real native-owner test vectors. Current timestamp/body HMAC alone does not establish a full v2 signing envelope; do not silently alter provider contract.
2. Live/staging artifacts and database cannot be inferred from source. Need exact plugin/runtime, deployed package checksum, schema columns/indexes, `wp_options` migration state, log, provider registration, DB engine and transaction guarantees.
3. Staging concurrency test for guest MySQL locks, cache/provider health, replay races, signed native outcomes, rollback and migration cutovers is still missing.
4. Required File 00/02/09/17/18/19/20/21/24/25 real manifests, storage/scanner/email/key provider attestations, and optional CF-01/CF-03/File 26 when enabled have not been independently accepted.
5. Future24 foundations do not establish 24 complete user-facing/provider/mobile features. Provider/migration/privacy/QA approval required for each activation.
6. Hostinger-equivalent staging, real-browser RTL/accessibility, performance/load/soak, independent security, full backup/restore, rollback, operational staffing, Founder exact-artifact approval and live re-test are outstanding.

## Mandatory final truth fields
- Main HEAD at this audit: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Corrective head: GitHub Draft PR #10 (re-evaluate SHA after any documentation change).
- Deployed Version: **unverified**.
- DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not verified**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No merge, deploy, live state change or resolved-production claim was performed.
