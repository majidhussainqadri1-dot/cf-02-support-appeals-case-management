# C2-N — November 2026 V12.3 Corrective Coding Change Control

## Identity and scope

- Source baseline: `main` commit `bd52b96af28ce775a18860142aec84a62fb9b803`.
- Target branch: `agent/cf02-v12-3-alignment-20261115`.
- Governing requirement source: Master Plan V12.3, CF-02 pp. 217–223; File 25 central visual rules; prior CF-02 v1.0.
- Candidate plugin version: `1.0.0-rc.8`; existing schema/contract remain `1.3.0` / `1.3.0`.
- Proposed status: repository code-review candidate only. Does **not** authorize automatic deployment or enable dormant runtime.

## Change impact

1. Files `CompleteFrontendSurfaces.php` and `cf02-guest.js` implement public low-sensitivity pre-intake, an encrypted, same-tab 10-minute continuation and a second authenticated confirmation. Login/session remains with File 00. Emergency diversion never creates an ordinary case.
2. File `cf02-frontend.css` aligns canonical green `#087A4E`, dark mode, readable inputs and File 25 logical responsive rules.
3. Files `CompleteAdminSurfaces.php`, `CompleteAdminScript.php`, `cf02-admin.js` and `cf02-admin.css` move the authorization-scoped operations UI to external scripts/styles for CSP compatibility, without broadening access or adding a second shell.
4. Urdu `.po`, `.pot` and compiled `.mo` catalogs are synchronized with the added guest/admin UI.
5. Release manifests, workflow versions, README, staging/readiness documents and regression assertions use distinct rc.8 identity and contract 1.3.0.
6. C2-N tests check domain safety, source traceability, JS grammar, i18n compilation, administrator CSP boundaries, release correctness and two fresh review suites.

## Database/API and migration

- Database schema change: **none**. `1.3.0` is still the version specified by `SchemaCompletion`.
- Public contract change: **none**. Existing `/guest/intake` and `/guest/continue` are now surfaced without new REST owners.
- New persistent browser data: a time-limited, encrypted low-sensitivity guest token in **same-tab sessionStorage only**, erased on continuation, discard, expiry or invalidation. No private C4/C5 offline cache or URL token.
- Foreign companion writes: **none**. Native-owner command/reconciliation boundaries are unchanged.
- Release migration: a version-only upgrade requires Hostinger staging activation and real DB/schema parity proof; not assumed completed.

## Rollback and promotion

1. Build deterministic rc.8 candidate tied to exact final branch SHA, ZIP hash, SBOM and provenance. Keep rc.7 source/package artifact separate.
2. Do not force-enable runtime. Observe existing `ActivationGate` fail-closed evidence before any staging experiment.
3. Stage with a sanitized production-like backup; rehearse restore and rollback. Verify File 00 session, File 20 routes, File 19 delivery and scanner/owner contracts.
4. Execute six native CF02-NJ journeys, adverse-path access, GDPR/privacy/retention, emergency diversion, RTL/LTR and screen-reader/device tests.
5. If any external gate fails, stop promotion and preserve original package and source of truth; do not silently patch live or overwrite production records.
6. Obtain exact-artifact Founder approval only after named independent review and zero unresolved Critical/High defects.

## Explicitly unresolved

The connector cannot inspect the deployed Hostinger plugin, logs, options or DB because no WordPress site is yet connected in WPVibe. Exact deployed version/code remain unverified. Original native-owner/provider contracts, operator coverage and acceptance of Future24 user-facing transports are likewise unproven. **No deployed defect is marked resolved.**
