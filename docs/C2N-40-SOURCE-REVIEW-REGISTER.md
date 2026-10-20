# CF-02 C2-N — Forty Focused Source Reviews and Corrective Register

## Governing interpretation
This register records **40 focused code/domain source reviews** on the exact repository candidate, not 40 independent full-repository review/fix cycles. A source inspection is not proof of runtime completeness or external acceptance. Each line identifies the code file inspected, source observation and residual proof needed. The accompanying 40-check cross-plan CI suite is a separate automated mechanism and cannot be substituted for full integration and security tests.

- Starting `main`: `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Candidate scope: runtime `1.0.0-rc.7`, schema `1.3.0`, contract `1.3.0`.
- Governing documents: central governing plan, CF-02 conditional plan (including Future24 amendment dated 2026-09-08), and native File 17/File 18/CF-01 companion boundaries.
- Current work: draft PR #10, no staging/live changes.
- No automatic promotion/merge: staging, provider, migration, restore, security, accessibility, exact deployment parity, real DB and Founder release gates remain explicit.

## Review register
| Review | Focus | Source examined | Finding / evidence boundary | Result |
|---:|---|---|---|---|
| 01 | Conditional activation | `src/Plugin.php` | Fail-closed dormant branch present; real approval not verified | SOURCE-REVIEW |
| 02 | Operational release proof | `src/Activation/OperationalEvidenceRegistry.php` | Environment-specific evidence fields exist; external artifacts pending | EXTERNAL-BLOCK |
| 03 | Companion owners | `src/Contracts/RequiredCompanionContracts.php` | 17 canonical records; cross-file documentation reconciled | FIXED-DOC |
| 04 | Identity binding | `src/Authorization/WordPressPrincipalContextFactory.php` | Actor-bound File 00 assertion checked; live issuer unverified | SOURCE-REVIEW |
| 05 | Purpose/field authorization | `src/Authorization/PurposeBoundAccessPolicy.php` | Capabilities, assignment, purpose and C4/C5 rules inspected | SOURCE-REVIEW |
| 06 | REST purpose/If-Match | `src/Infrastructure/WordPress/RequestGuard.php` | Exact purpose and integer overflow corrected; dynamic regressions passed | FIXED-TESTED |
| 07 | Guest intake rate abuse | `src/Infrastructure/WordPress/GuestIntakeController.php` | MySQL named lock serializes rate checks; emergency diversion precedes throttle; synthetic tests passed; real concurrent load pending | FIXED-TESTED |
| 08 | Guest encrypted token | `src/Intake/GuestContinuationToken.php` | Secretbox expiry and envelope checks located; real browser journey pending | SOURCE-REVIEW |
| 09 | REST input sanitization | `src/Infrastructure/WordPress/ApiInput.php` | Bounded refs/content and secret filtering reviewed; field matrix pending | SOURCE-REVIEW |
| 10 | Provider signed callbacks | `src/Infrastructure/WordPress/ProviderWebhookController.php` | Webhook intake channel normalized to valid internal system message channel; timestamp/body HMAC still lacks explicit route/method signature binding | PARTIAL-FIX |
| 11 | Intake idempotency | `src/Infrastructure/WordPress/IntakeRepository.php` | Transactional replay with requester/payload fingerprint inspected | SOURCE-REVIEW |
| 12 | Case/requester ownership | `src/Infrastructure/WordPress/OperationsRepository.php` | caseForActor assignment and requester checks inspected; environment IDOR pending | SOURCE-REVIEW |
| 13 | Attachment transitions | `src/Attachment/AttachmentStateMachine.php` | Quarantine and scan transitions inspected; real scanner pending | EXTERNAL-BLOCK |
| 14 | One-time attachment access | `src/Infrastructure/WordPress/OperationsRepository.php` | Token hashes and case authorization inspected; real private delivery pending | EXTERNAL-BLOCK |
| 15 | Malware callback | `src/Infrastructure/WordPress/ProviderWebhookController.php` | Signed scanner/redaction endpoints inspected; provider identity pending | EXTERNAL-BLOCK |
| 16 | Message visibility | `src/Thread/CaseThread.php` | Requester/internal/restricted projections reviewed at source level | SOURCE-REVIEW |
| 17 | SLA pause/resume | `src/Sla/SlaClock.php` | Versioned pause/resume and chronology paths inspected | SOURCE-REVIEW |
| 18 | Agent assignment | `src/Assignment/AssignmentRouter.php` | Queue, language, role, skills and capacity selection inspected | SOURCE-REVIEW |
| 19 | Donor parity | `src/Governance/ServiceEqualityPolicy.php` | Donation/ranking priority signals prohibited in policy | SOURCE-REVIEW |
| 20 | Appeal reviewer independence | `src/Appeal/ReviewerAssignmentPolicy.php` | Prior involvement and organization-unit checks inspected | SOURCE-REVIEW |
| 21 | Appeal deadlines | `src/Appeal/AppealEligibilityPolicy.php` | Standing, recognized grounds, exception and deadline constraints inspected | SOURCE-REVIEW |
| 22 | Native outcome reconciliation | `src/Integration/ImplementationReconciler.php` | Owner/action/ref/version/outcome reconciliation checks inspected | SOURCE-REVIEW |
| 23 | Native command | `src/Integration/NativeOwnerCommand.php` | Canonical owner command and expected version guardrails inspected | SOURCE-REVIEW |
| 24 | Adverse decision notice | `src/Governance/AdverseDecisionNotice.php` | Reasons, policy version, appeal route and deadline fields inspected | SOURCE-REVIEW |
| 25 | Retention planner | `src/Retention/PurgePlanner.php` | Hold-aware retention planning inspected; live retention schedule pending | EXTERNAL-BLOCK |
| 26 | Legal hold | `src/Evidence/LegalHold.php` | Case/category legal hold lifecycle inspected | SOURCE-REVIEW |
| 27 | Secure export | `src/Evidence/SecureExportService.php` | Purpose, assigned-case and export filename controls inspected | SOURCE-REVIEW |
| 28 | Repair/audit | `src/Infrastructure/WordPress/RepairService.php` | Repair audit failure no longer returns verified success; regression static guard green | FIXED-TESTED |
| 29 | Migration dual read | `src/Migration/DualReadReconciler.php` | Comparison and cutover checks located; production/staging rehearsal pending | EXTERNAL-BLOCK |
| 30 | Managed key owner | `src/Infrastructure/WordPress/ManagedKeyRingProvider.php` | Provider readiness, health, audience/version checks inspected | EXTERNAL-BLOCK |
| 31 | Cipher primitives | `src/Security/DataCipher.php` | Sodium authenticated encryption and v1/v2 key rotation paths inspected | SOURCE-REVIEW |
| 32 | Atomic key rotation | `src/Infrastructure/WordPress/EncryptionRotationService.php` | Data update plus mandatory audit ledger made atomic; failure rollback regression green | FIXED-TESTED |
| 33 | Notification outbox | `src/Delivery/DeliveryOutbox.php` | Idempotency/ready/dead-letter API present; File 19 real delivery pending | EXTERNAL-BLOCK |
| 34 | Worker retries | `src/Infrastructure/WordPress/RuntimeWorker.php` | Retry/dead-letter logic inspected; load/soak and provider outage pending | EXTERNAL-BLOCK |
| 35 | Signed pagination cursors | `src/Search/CursorCodec.php` | Signed, scoped, expiring keyset cursor checked at source | SOURCE-REVIEW |
| 36 | Low-bandwidth/mobile | `src/Future/ContinuityExperienceIntelligence.php` | Future24 foundation only, not complete native/PWA UI/provider runtime | IMPLEMENTATION-GAP |
| 37 | All Future24 identities | `src/Future/FeatureCatalog.php` | 24 gated declarations; production implementations not established | IMPLEMENTATION-GAP |
| 38 | Deterministic packaging | `build/verify_package.py` | ZIP/source parity validation present; exact-head release job successful | CI-VERIFIED |
| 39 | Private cache/crawler safety | `src/Infrastructure/WordPress/Runtime.php` | Private page no-store/noindex headers located; reverse proxy acceptance pending | EXTERNAL-BLOCK |
| 40 | Shell and route contracts | `src/Infrastructure/WordPress/RouteRegistrar.php` | File 01/20 routing contract registration present; real companion handshake pending | EXTERNAL-BLOCK |

## Proven corrective work in this PR
- **C2N-DEF-01 — purpose rewriting and numeric overflow:** RequestGuard previously silently sanitized the original purpose, and PHP cast could clamp oversized If-Match version values. Corrected strict raw purpose checks (including optional nonblank) and numeric bounds. Added runnable `tests/c2n-runtime-security-regressions.php`.
- **C2N-DEF-02 — repair audit fail-open:** RepairService previously reported success even when mandatory repair ledger insert failed. It now validates serialization and throws on audit persistence failure before publishing last-verified repair evidence. Runtime database-repair atomicity and real WordPress evidence still require environment-specific testing.
- **C2N-DEF-03 — key rotation audit mismatch:** EncryptionRotationService previously incremented rotated count before verifying its mandatory audit ledger. Encrypted row update and audit insertion now share a transaction, roll back on audit failure, and count success only after COMMIT. Added executable success/failure simulation in `tests/c2n-rotation-atomicity.php`.
- **C2N-DEF-04 — guest emergency and rate limiting:** Guest emergency diversion now runs before ordinary throttling; advisory MySQL locks serialize rate-counter read/write, and lock/cache-write failures deny requests. Synthetic regression tests: `tests/fresh-cf02-guest-webhook-safety.php`. Real concurrent staging/load evidence remains outstanding.\n- **C2N-DEF-05 — signed webhook channel:** `webhook` transport now maps to internal `system` message channel; transport provenance remains in inbound receipt evidence. Synthetic regressions added.\n- **C2N-DOC-01 — contract documentation:** Reconciled stale `1.1.0` documents with canonical `1.3.0`, and corrected 17-provider/companion readiness manifest coverage.
- Previously added `tests/crossplan-40-review.php` remains a separate 40-check source/plan consistency suite.

## Open engineering and external barriers (NOT resolved)
1. **C2N-OPEN-01 (guest-rate deployment evidence):** The new named-lock limiter is coded and synthetic-tested. Real MySQL/MariaDB named-lock availability, object-cache consistency, concurrency, denial behavior and retention must pass staging load tests before live acceptance.
2. **C2N-OPEN-02 (webhook envelope version):** The callback HMAC currently covers timestamp and body while actual method/route/key-id/purpose binding depends on external key scoping; explicit signature-envelope protocol review and coordinated provider compatibility tests are necessary before tightening deployed callbacks.
3. **C2N-OPEN-03 (Future24):** 24 guarded PHP foundations are not evidence of 24 complete publicly usable integrations, UIs, providers, mobile builds, accessibility acceptance or production operations.
4. **C2N-OPEN-04 (deployed reality):** Exact deployed plugin source/package, WordPress active version, database schema, migration state, provider manifests and runtime logs remain unverified. Never diagnose a live bug by assuming GitHub equals deployment.
5. **C2N-OPEN-05 (release proof):** Stage with real Files 00/02/09/17/18/19/20/21/24/25, optional File 26/CF-03/CF-01 when enabled, and real secret/attachment/email providers. Demonstrate backups, migrations, rollback, independent security, manual accessibility, load and Founder approval.

## Release/incident truth
- `Repository HEAD`: main `bd52b96af28ce775a18860142aec84a62fb9b803` (unchanged before this review).
- `Deployed Version`: unverified.
- `DB Version`: unverified.
- `Migration State`: unverified.
- `Live Verification Status`: not verified.
- No blocking security issue is hidden behind a successful repository test.
