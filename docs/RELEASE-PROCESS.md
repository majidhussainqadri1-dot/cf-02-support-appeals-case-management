# CF-02 Deterministic Release Process

## Candidate identity

- Package slug: `cf-02-support-appeals-case-management`
- Candidate version: `1.0.0-rc.6`
- Schema: `1.3.0`
- Contract: `1.1.0`
- Plan: `1.0`
- Canonical identity source: `release/manifest.json`
- Source identity: exact Git commit used by the workflow

No workflow, runbook or test may independently hard-code a conflicting current candidate identity. `build/verify_release_identity.py` is a mandatory release gate.

## Automated build

The release workflow performs:

1. strict Composer metadata validation;
2. release-identity and three-plan traceability verification;
3. complete PHP syntax and repository test matrix;
4. public-repository safety scan;
5. deterministic allowlisted ZIP creation;
6. package-manifest generation with per-file SHA-256 hashes;
7. source/package parity verification;
8. SPDX 2.3 SBOM generation;
9. provenance statement generation;
10. SHA-256 checksum generation;
11. second clean build and byte-for-byte comparison;
12. artifact upload for the exact workflow commit.

Development-only surfaces (`.git`, `.github`, `build`, `docs`, `tests`, `release`, `vendor`, `node_modules`, `.env`) are excluded from the WordPress ZIP.

## Local reproduction

```bash
python3 build/verify_release_identity.py
python3 build/verify_traceability.py
python3 build/security_scan.py
python3 build/package.py --source-sha "$(git rev-parse HEAD)"
python3 build/verify_package.py --source-sha "$(git rev-parse HEAD)"
sha256sum -c dist/SHA256SUMS
```

A second clean checkout of the same commit must produce the same ZIP SHA-256. Any mismatch blocks promotion and requires a documented defect investigation.

## Artifact set

- `cf-02-support-appeals-case-management-1.0.0-rc.6.zip`
- `cf-02-support-appeals-case-management-1.0.0-rc.6.spdx.json`
- `cf-02-support-appeals-case-management-1.0.0-rc.6.provenance.json`
- `SHA256SUMS`

The provenance JSON is a generated statement, not a cryptographic signature or independent attestation. Formal signing requires an approved private signing process outside the public repository.

## Promotion gates

A generated package may be called `Packaged candidate` only after package verification succeeds. It may not be called `Staging-Accepted`, `Live-Deployed` or `Operational` until the corresponding external evidence is complete.

Before staging promotion, record the exact source SHA and PR, exact RC6 ZIP checksum, manifest, SBOM/provenance checksums, review/fix evidence, backup/rollback readiness and zero unresolved blocking defects.

## Release invalidation

Any source, dependency, contract, security, staging or provider change invalidates the previous package. Rebuild, re-verify, repeat affected reviews, recalculate checksums and update the evidence register.
