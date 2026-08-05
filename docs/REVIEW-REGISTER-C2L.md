# C2-L Two Independent Review–Fix Rounds

## Review 1 — Release identity and evidence integrity

**Defects found**

1. Current runtime was RC5 while `RELEASE-PROCESS.md` and `STAGING-ACCEPTANCE.md` still named RC2.
2. Lifecycle/release workflows hard-coded the package version independently of the release manifest.
3. Release tests checked file presence but not operative documentation identity.
4. Current migration wording still referred to schema 1.2.

**Corrections**

- Advanced the corrected candidate to RC6.
- Made `release/manifest.json` the canonical workflow identity source.
- Added a fail-closed release-identity verifier and C2-L regressions.
- Aligned operative release, staging, readiness, change-control and traceability documents with schema 1.3/contract 1.1.

## Fresh adversarial Review 2 — False completeness and traceability

**Defects found**

1. The textual RTM did not provide a machine-verifiable exact 34-ID evidence set.
2. CI did not prove that every traceability path existed.
3. A future edit could falsely mark external staging evidence as passed without a structured guard.

**Corrections**

- Added `release/traceability.json` with exact CF02-FR-001…034 ordering and source/test/migration/security evidence.
- Added `build/verify_traceability.py` and adversarial tests.
- Enforced `pending-external` for staging evidence and kept production-readiness gates explicitly not started.

Both rounds require full regression and exact-head GitHub Actions before RC6 may be called Automated-QA Green or Packaged.
