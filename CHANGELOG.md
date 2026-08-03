# Changelog

All notable CF-02 repository changes are recorded here. Status words follow the platform's truthful completion law: coded, packaged, automated-QA, staging-accepted, live-deployed and operational are separate states.

## 1.0.0-rc.2 — Packaged candidate

### Added

- Deterministic, allowlisted WordPress package builder.
- Package manifest containing exact source SHA and per-file SHA-256 hashes.
- Package/source parity and archive-safety verifier.
- SPDX 2.3 SBOM and unsigned provenance statement.
- SHA-256 checksum evidence and exact-head GitHub artifact workflow.
- Public repository secret/private-key and forbidden-file safety scan.
- WordPress `readme.txt`, proprietary license notice and non-destructive uninstall law.
- Clean WordPress/MariaDB lifecycle workflow covering install, fail-closed activation, deactivate/reactivate and safe uninstall preservation.
- Production-readiness, staging, security/privacy, release and backup/restore/rollback runbooks.
- Release engineering regression suite across PHP 8.1–8.4.

### Corrected during review

- Release workflows now bind packages to the exact pull-request head, not GitHub's synthetic merge commit.
- Generated package-manifest entries are included in the globally sorted deterministic archive order.
- Static uninstall safety checks no longer fail on descriptive comment text.
- WordPress smoke testing now distinguishes activation-hook `pending` from the stable fail-closed `dormant` runtime state and requires explicit denial reasons.

### Status

- Coded: yes.
- Deterministically packaged candidate: yes.
- Automated repository QA: yes.
- Clean generic WordPress lifecycle smoke: yes.
- Hostinger staging accepted: no.
- Live deployed: no.
- Operational: no.

## 1.0.0-rc.1 — Complete coding candidate

- Completed C2-A through C2-H repository implementation.
- Added two review/fix rounds per phase and complete PHP 8.1–8.4 test matrix.
- Added fail-closed WordPress runtime/schema, case/appeal/SLA/security/migration/resilience foundations.
- Did not claim deterministic packaging, Hostinger staging, live deployment or operational acceptance.
