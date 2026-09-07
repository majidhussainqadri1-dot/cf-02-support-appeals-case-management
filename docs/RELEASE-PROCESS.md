# CF-02 Deterministic Release Process

## Candidate identity

- Package slug: `cf-02-support-appeals-case-management`
- Candidate version: `1.0.0-rc.6`
- Schema: `1.3.0`
- Contract: `1.1.0`
- Plan: `1.0`
- Source of truth: exact Git commit on the controlled candidate branch/PR

## Automated build

The release workflow performs strict Composer validation, complete PHP syntax/regression tests, public-repository safety scan, deterministic allowlisted ZIP creation, package manifest with per-file SHA-256 hashes, source/package parity verification, SPDX 2.3 SBOM, provenance statement, checksum generation and artifact upload for the exact workflow commit.

Development-only surfaces (`.git`, `.github`, `build`, `docs`, `tests`, `release`, `vendor`, `node_modules`, `.env`) are excluded from the WordPress ZIP.

## Local reproduction

```bash
python3 build/security_scan.py
python3 build/package.py --source-sha "$(git rev-parse HEAD)"
python3 build/verify_package.py --source-sha "$(git rev-parse HEAD)"
sha256sum -c dist/SHA256SUMS
```

A second clean checkout of the same commit must produce the same ZIP SHA-256 when the same source timestamp is used. Any mismatch blocks promotion and requires defect investigation.

## Artifact set

- `cf-02-support-appeals-case-management-1.0.0-rc.6.zip`
- `cf-02-support-appeals-case-management-1.0.0-rc.6.spdx.json`
- `cf-02-support-appeals-case-management-1.0.0-rc.6.provenance.json`
- `SHA256SUMS`

The provenance JSON is generated evidence, not a cryptographic signature or independent attestation. Formal signing requires an approved private signing process outside the public repository.

## Promotion gates

A verified generated package may be called **Packaged candidate**. It may not be called `Staging-Accepted`, `Live-Deployed`, or `Operational` until the corresponding external evidence is complete.

Before staging promotion record exact source SHA/PR, workflow runs/jobs, ZIP checksum/manifest, SBOM/provenance checksums, C2-L two fresh review results, zero unresolved blocking defects, backup/rollback readiness and explicit staging-entry approval.

## Release invalidation

Any source, dependency/contract, security advisory, corrected defect, failed staging test, provider drift or plan-governance change invalidates prior package evidence. Rebuild, re-verify, repeat affected reviews, recalculate checksums and update the evidence register.
