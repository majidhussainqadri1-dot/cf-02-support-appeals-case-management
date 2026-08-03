# CF-02 Review and Correction Register

## Completed earlier phases

C2-A, C2-B and C2-C each completed two independent review/fix rounds. Their retained regression suites cover activation evidence, companion ownership, taxonomy/configuration, intake replay, case/thread/attachment lifecycle, resolution/reopen, assignment, SLA, escalation, queue health and major-incident privacy.

## C2-D Round 1 — Authorization, command and evidence integrity

Status: corrected and retested.

1. Replaced role-only assumptions with `AccessContext` and purpose-bound object/field/action authorization.
2. Required assignment/queue relationship, versioned capability, field class, sensitive approval, recent authentication, expiry and suspension checks.
3. Added canonical mutation fingerprints, replay windows and idempotency collision rejection.
4. Bound native-owner commands to exact payload fingerprints and owner/action/object identity.
5. Added retry, dead letter, compensation and native implementation reconciliation.
6. Added authorized minimized case search, bounded cursors, safe filters, legal holds, secure exports and tamper-evident audit chaining.

## C2-D Round 2 — Fresh adversarial authority and disclosure review

Status: corrected and retested.

1. Blocked expired/suspended contexts and incompatible purposes.
2. Rejected sensitive access without both specialist approval and recent authentication.
3. Prevented one command idempotency key from changing owner, action, object or payload.
4. Exposed native owner/version/outcome drift instead of false reconciliation.
5. Rejected cursor tampering, hidden-result count leakage and sensitive audit-context keys.
6. Added authenticated encryption for persisted private messages and bounded export secret detection.

## C2-E Round 1 — Appeal fairness and independence

Status: corrected and retested.

1. Added standing, deadline, grounds, evidence and documented exception eligibility.
2. Added competence, availability, prior-involvement, conflict and sensitive-clearance reviewer assignment.
3. Preserved the original decision as an immutable dossier hash with append-only submissions.
4. Added reasoned outcomes, findings, evidence considered, effective actions and further rights.
5. Required native decision and implementation references before closure.

## C2-E Round 2 — Fresh adversarial appeal review

Status: corrected and retested.

1. Blocked self-review and prior-decision involvement.
2. Rejected late exceptions without an accessibility, representation or new-evidence basis.
3. Required a further path for ineligible appeals.
4. Rejected modifying/overturning outcomes without effective actions.
5. Kept appeals open when implementation references drifted.
6. Preserved non-retaliation and representative/accessibility paths.

## C2-F Round 1 — Quality, configuration, automation and degraded delivery

Status: corrected and retested.

1. Added complete accuracy/empathy/compliance/security/accessibility quality rubric and correction tracking.
2. Made rubric validation order-independent and rejected missing or unsupported dimensions.
3. Added approved/versioned/expiring knowledge suggestions with suggestion-only boundaries.
4. Added optional feedback, opt-out, secret rejection and low-volume identity suppression.
5. Added versioned staged configuration, validation, dual approval, activation and rollback.
6. Added durable outbox, exponential retry, dead letter, consented alternate channels and hold-aware retention.

## C2-F Round 2 — Fresh adversarial automation and abuse review

Status: corrected and retested.

1. Prohibited autonomous final appeal, identity, refund, clinical, safety and native-owner actions.
2. Required human confirmation for governed closure and human review for every suggestion.
3. Added progressive guest/authenticated rate limits, challenge thresholds and bounded penalties without hiding emergency direction.
4. Prevented outbox idempotency keys from binding changed content.
5. Added governed task lifecycle with dependency, outcome and optimistic-concurrency controls.
6. Prevented feedback and templates from carrying OTPs, credentials or secret variables.

## C2-G Round 1 — Migration mapping and parity

Status: corrected and retested.

1. Added immutable source-to-target mapping, source/target hashes and isolated failure records.
2. Added strict dual-read field parity and explicit divergence evidence.
3. Required complete record counts, zero case/SLA/appeal divergence and rehearsed rollback before cutover.

## C2-G Round 2 — Fresh adversarial migration review

Status: corrected and retested.

1. Prevented source mappings from being rebound to a different target.
2. Treated scalar type drift as real divergence.
3. Blocked cutover for incomplete counts, unexplained appeal/SLA drift or absent rollback proof.
4. Preserved source truth and reversible shadow/dual-read boundaries.

## C2-H Round 1 — WordPress runtime, persistence and recovery

Status: corrected and retested.

1. Added idempotent schema installation for cases, messages, attachments, assignments, SLA, appeals, dossiers, commands, outbox, tasks, quality, feedback, holds, configuration, migration, retention and audit.
2. Added transactional intake replay persistence and requester-scoped case APIs.
3. Corrected encrypted-message replay so random encryption nonces cannot create false idempotency collisions.
4. Added bounded scheduler registration/cleanup, no-store/noindex private headers and accessible public/admin shells.
5. Added load budgets, authenticated restore evidence, training readiness and fail-closed release gates.

## C2-H Round 2 — Fresh adversarial runtime and release review

Status: corrected and retested.

1. Rejected injected database prefixes and incomplete restore scopes.
2. Required sodium authenticated encryption and rejected tampered ciphertext.
3. Cleared every scheduled-hook instance on deactivation.
4. Added complete schema coverage tests and prohibited release evidence from defaulting to true.
5. Kept staging, package parity, backup/restore, browser accessibility, real-provider and Founder approval as explicit external gates.

## Production Readiness Round 1 — Packaging, uninstall and evidence integrity

Status: corrected and retested.

1. Added an allowlisted deterministic WordPress packager, package verifier, per-file hashes, SPDX SBOM, provenance statement and SHA-256 evidence.
2. Added public-repository secret/private-key scanning and forbidden-package-path controls.
3. Added non-destructive uninstall safeguards and lifecycle regression tests.
4. Corrected a scanner false positive caused by destructive-operation keywords in comments, while preserving the substantive non-destructive law.
5. Added staging, deployed security/privacy, backup/restore/rollback and production-readiness runbooks.
6. Promoted the packaged candidate identity to `1.0.0-rc.2` without changing schema `1.1.0` or plan `1.0`.

## Production Readiness Round 2 — Fresh artifact, CI and lifecycle adversarial review

Status: corrected and retested.

1. Found that ordinary pull-request workflows could bind artifacts to GitHub's synthetic merge SHA rather than the actual branch head; both packaging and WordPress lifecycle workflows now resolve, checkout and verify the exact PR head SHA.
2. Found that the generated package manifest was appended after otherwise sorted entries; the packager now sorts all payloads, including generated evidence, in one deterministic pass.
3. Found that the lifecycle test expected the transient activation-hook state `pending` after WordPress had already loaded the plugin; the test now correctly requires the stable fail-closed `dormant` state with explicit denial reasons.
4. Fresh WordPress 7.0.2/PHP 8.3/MariaDB 10.11 smoke testing then passed package install, fail-closed activation, deactivate/reactivate idempotency and non-destructive uninstall preservation.
5. The complete PHP 8.1–8.4 matrix, release regressions, repository safety scan, deterministic double-build comparison, package verification and packaged PHP syntax passed after correction.

## Automated evidence

Historical complete-matrix runs include `30848651372` and `30849210812`. The packaging and lifecycle review used exact-head workflows and repeatedly reopened review on each failure rather than treating a green earlier run as final evidence. Final exact-head run identities are recorded in the Draft PR evidence comment because any later source commit invalidates an earlier package checksum.

## Truthful completion state

The repository contains the complete governed coding candidate and its two review/fix suites per phase. It now also contains a deterministic packaged release-candidate process and clean WordPress lifecycle smoke coverage. Real companion adapters, scanner/storage, email/notification providers, Hostinger staging, browser/device/accessibility execution, production migration, load/soak, backup/restore, rollback rehearsal, observation window and Founder exact-version acceptance remain external evidence gates. Any new finding, dependency drift or staging defect reopens review.
