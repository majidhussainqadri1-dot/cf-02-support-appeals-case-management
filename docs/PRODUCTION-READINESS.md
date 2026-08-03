# CF-02 Production Readiness Evidence Register

## Status boundary

Runtime candidate: `1.0.0-rc.2`  
Plan: `1.0`  
Schema: `1.1.0`

This register prepares evidence collection. It does not itself prove staging, live deployment or operational acceptance.

## Evidence states

Each gate must be recorded as one of: `not-started`, `in-progress`, `passed`, `failed`, `blocked`, or `Founder-accepted-risk`. A gate cannot be inferred from another gate.

| Gate | Required evidence | Current state |
|---|---|---|
| Source identity | Exact branch, commit SHA, clean diff, PR and review record | automated candidate |
| Deterministic package | Canonical ZIP, manifest, source/package parity, SHA-256 | automated candidate |
| SBOM/provenance | SPDX SBOM, provenance statement, dependency inventory | automated candidate |
| Public repository safety | Obvious secret/private-key scan and forbidden-file scan | automated candidate |
| Fresh WordPress lifecycle | Install, activate, deactivate, reactivate, uninstall preservation | automated candidate |
| Supported upgrade | Prior accepted schema/package to exact candidate | not-started |
| Companion contracts | Files 00/09/17/18/20/21/24/25 exact version and behavior | not-started |
| Provider contracts | Scanner, object storage, email/notification and any required adapters | not-started |
| Security | IDOR/BOLA/CSRF/XSS/SQLi/SSRF/replay/race/cache leakage and privilege tests | not-started |
| Privacy | minimization, export, erasure, holds, retention, logs/search/telemetry leakage | not-started |
| Accessibility | keyboard, screen reader, contrast, zoom, reflow, RTL and reduced motion | not-started |
| Performance/resilience | load, queue saturation, timeout, partial outage, recovery and alerts | not-started |
| Migration | inventory, dry run, dual read, reconciliation, cutover and rollback | not-started |
| Backup/restore | database/configuration/private object evidence and downstream reconciliation | not-started |
| Staffing/operations | named roles, coverage, escalation, training and runbooks | not-started |
| Observation window | staged monitoring with zero unresolved critical/high defects | not-started |
| Founder approval | exact commit, ZIP checksum, evidence package and residual risks | not-started |

## Mandatory evidence metadata

Every manual artifact must contain:

- exact commit and package SHA-256;
- environment name and URL classification without exposing secrets;
- WordPress, PHP, database and relevant provider versions;
- test date/time in Pakistan Standard Time and UTC;
- tester role, not private credentials;
- preconditions, steps, expected result and actual result;
- screenshots/log references with sensitive fields redacted;
- defect IDs, fixes, retest results and unresolved residual risk;
- approver and approval scope.

## Release law

`Coded`, `Automated-QA Green`, `Packaged`, `Staging-Accepted`, `Live-Deployed` and `Operational` remain separate statuses. Missing evidence is `unknown/not-started`, never an implicit pass.
