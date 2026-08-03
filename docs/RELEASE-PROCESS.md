# CF-02 Deterministic Release Process

## Candidate identity

- Package slug: `cf-02-support-appeals-case-management`
- Candidate version: `1.0.0-rc.2`
- Schema: `1.1.0`
- Plan: `1.0`
- Source of truth: exact Git commit on the controlled pull-request branch

## Automated build

The release workflow performs:

1. strict Composer metadata validation;
2. complete PHP syntax and repository test matrix;
3. public-repository safety scan;
4. deterministic allowlisted ZIP creation;
5. package-manifest generation with per-file SHA-256 hashes;
6. source/package parity verification;
7. SPDX 2.3 SBOM generation;
8. provenance statement generation;
9. SHA-256 checksum generation;
10. artifact upload for the exact workflow commit.

Development-only surfaces (`.git`, `.github`, `build`, `docs`, `tests`, `release`, `vendor`, `node_modules`, `.env`) are excluded from the WordPress ZIP.

## Local reproduction

```bash
python3 build/security_scan.py
python3 build/package.py --source-sha "$(git rev-parse HEAD)"
python3 build/verify_package.py --source-sha "$(git rev-parse HEAD)"
sha256sum -c dist/SHA256SUMS
```

A second clean checkout of the same commit must produce the same ZIP SHA-256 when the same source timestamp is used. Any mismatch blocks promotion and requires a documented defect investigation.

## Artifact set

- `cf-02-support-appeals-case-management-1.0.0-rc.2.zip`
- `cf-02-support-appeals-case-management-1.0.0-rc.2.spdx.json`
- `cf-02-support-appeals-case-management-1.0.0-rc.2.provenance.json`
- `SHA256SUMS`

The provenance JSON is a generated statement, not a cryptographic signature or independent attestation. Formal signing requires an approved private signing process outside the public repository.

## Promotion gates

A generated package may be called `Packaged candidate` only after package verification succeeds. It may not be called `Staging-Accepted`, `Live-Deployed` or `Operational` until the corresponding external evidence is complete.

Before staging promotion, record:

- exact source SHA and PR;
- workflow run and job results;
- ZIP checksum and manifest;
- SBOM/provenance checksums;
- review/fix rounds and zero unresolved blocking defects;
- staging entry approval and backup/rollback readiness.

## Release invalidation

Any source change, dependency/contract change, security advisory, failed staging test, provider drift or corrected defect invalidates the previous package. Rebuild, re-verify, repeat affected reviews, recalculate checksums and update the evidence register.
