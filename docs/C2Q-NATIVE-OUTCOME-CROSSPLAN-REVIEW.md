# CF-02 C2-Q — Forty New Native-Outcome Adversarial Reviews and Cross-Plan Closure Register

## Exact state and controlling sources
- Reviewed GitHub main before changes: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Continuing **Draft PR #10**; no merge and no deployment. Candidate `1.0.0-rc.7`, schema and contract `1.3.0`.
- Primary module: **CF-02 Support, Appeals and Case Management Conditional Complete Master Plan, Future24 amended 2026-09-08**.
- Shared authority: **Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan (2026)**; native File 17 communication/report, File 18 marketplace, CF-01 clinical, CF-03 financial and the File 00/02/09/19/20/24/25 integration contracts.
- CF02-FR-022 requires CF-02 to **request** the native owner's change and reconcile the observed action/result. A native result without a valid version and evidence must not be mistaken for successful implementation.
- CF02-FR-002 requires signed, replay-safe omnichannel integration; CF02-FUT-022 adds institutional APIs/webhooks as **future conditional**, not an already approved full provider protocol.
- The project's **live-first exact deployed state** gate is authoritative; GitHub code is not a live runtime claim.

## Newly proven gap and root cause
The prior C2-P correction deployed `NativeResultVersion::fromPayload()` to the **REST signed callback** while the **background native-command worker** still applied `(int) ($result['native_version'] ?? 0)`, which can clamp/transform an invalid value. Worker also allowed `state='succeeded'` with an empty outcome reference, in contrast to the signed callback's check. These are **two sub-defects in one cross-path native-outcome parity finding**, not two full-system failures proven on live.

## Corrective implementation
- New `src/Integration/NativeOwnerOutcomePolicy.php` uses `NativeResultVersion::fromPayload()`, checks permitted states and minimum version, enforces exact UTF-8 and max **191-byte** outcome references, rejects control characters, and requires nonblank owner evidence when `succeeded`.
- Both `RuntimeWorker::processCommands()` and `ProviderWebhookController::nativeResult()` call this shared policy.
- The C2-P regression's callback-source assertion was updated to follow the shared validator, avoiding a false negative from the deliberate architecture change.
- **40 new executable edge-case reviews**, ten accepted vectors and thirty rejected vectors, in `tests/c2q-40-native-outcome-contract.php`, enforce policy plus exact use by BOTH paths. Added to Composer and PHP 8.1/8.2/8.3/8.4 GitHub CI.

## Bounded result from exact-head CI before this documentation commit
- **40/40 new native-outcome vectors passed**.
- **40/40 existing CF02-FR/central source-method witnesses passed**.
- **40/40 prior plan/companion consistency tests passed**.
- **35/35 signed inbound metadata checks passed**.
- Source exception-exposure pattern scan: **162 PHP files, 0 matching raw-exception patterns**.
- Release candidate, CI, WordPress lifecycle, active runtime integration and source-artifact workflows: **5/5 successful** at source head `bc5b8c67627e8794161184a9ba487c6b5261317d`.
- These are 40 **specific automated semantic test cases** on the found defect, *not* 40 independent comprehensive architecture reviews or end-to-end provider tests. Existing 40 focused source-area reviews and central coverage are documented separately in C2-N/C2-O.

## Pending code/integration and evidence obligations
1. **Provider signature binding**: current HMAC authenticates timestamp/raw body and uses purpose-scoped key lookup, but full canonical HTTP method, route, key-ID, native owner and source-owner binding needs versioned signed-envelope specs and actual provider test vectors. No unapproved compatibility-breaking protocol change was made.
2. **External truth**: Real File 00, File 02, File 09, File 17, File 18, File 19, File 20, File 21, File 24, File 25 owners and conditional File 26/CF-01/CF-03 approvals cannot be fabricated from CF-02 repository.
3. **Future24**: the 24 guarded domain foundations are **not** evidence of 24 finished UIs, providers, phone/PWA implementations or production operations. New persistence or transport requires separate design and activation evidence.
4. **Guest throttling**: MySQL advisory locks/cache races, signature replay storms and rate-limit fairness need real Hostinger-equivalent staging load tests.
5. **Live access**: WPVibe listing reported no connected sites. A connector attempt to `https://sabrihomeopathy.com` failed with **Cloudflare HTTP 530**. This proves that *this access path* is blocked; it does **not** prove which DNS, origin, WordPress or network hop is responsible.
6. **Production verification**: deployed plugin/version/hash, schema/columns, wp_options migration, database transactions, logs, real provider attestations, migration/rollback, manual security/accessibility, staffed operations and Founder exact-artifact approval are pending.

## Root cause, remediation, status categories
- **Source-level native outcome inconsistency**: evidenced; corrected on Draft PR; synthetic regression passed.
- **Newly found code-path inconsistency count this iteration**: 1 category, with 2 related checks (version canonicality and required success evidence).
- **Earlier PR source findings**: 10 previously recorded defect categories; for reporting avoid double counting the earlier native-version category as a separate new *type*.  The worker omission is a newly discovered **residual sub-defect within native-version validation**, plus a separate outcome-evidence sub-defect.
- **Merge/deployment**: not done.
- **Repository HEAD main**: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- **Deployed Version / DB Version / Migration State**: unverified.
- **Live Verification Status**: not performed.
- **Resolution**: source-level CI-green only, **not** Live-Resolved.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
