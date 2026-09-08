# CF-02 Production Readiness Evidence Register

## Status boundary

Runtime candidate: `1.0.0-rc.6`  
Plan: `1.0`  
Schema: `1.3.0`  
Contract: `1.1.0`

The latest rewritten central-plan and CF-02-plan coded surface is reconciled as a repository candidate. This register does not itself prove Hostinger staging, live deployment or operational acceptance.

## Evidence states

Each gate is independent: `not-started`, `in-progress`, `passed`, `failed`, `blocked`, or `Founder-accepted-risk`.

| Gate | Required evidence | Current code/repository state |
|---|---|---|
| Governing traceability | Central plan + CF02-FR/CEN/NJ/AJ owner obligations → code/tests | complete candidate; exact-head CI pending final commit evidence |
| Runtime command/query surface | canonical commands/queries plus safe guest continuation transport | complete candidate |
| Authorization/ownership | File 00 assertion, native-owner keys, JIT purpose/field access, no direct companion writes | complete candidate |
| Persistence/lifecycles | Schema 1.3.0, replay, events, attachments, SLA, appeals, holds, retention | complete candidate |
| Latest-plan coding review 1 | C2-L first corrective/adversarial review | coded as permanent CI gate; exact-head result pending |
| Latest-plan fresh review 2 | independent C2-L adversarial review | coded as permanent CI gate; exact-head result pending |
| Source identity | Exact branch/head/PR | pending final exact-head record |
| Deterministic package | ZIP, manifest, SBOM, provenance, checksums, parity | automated workflow; pending final exact-head artifact |
| Dormant lifecycle | clean install, fail-closed activation, reactivation, safe uninstall | automated controlled evidence |
| Active runtime integration | ready activation, schema, routes, intake/replay/persistence | automated controlled environment |
| Supported upgrade | prior accepted package/schema to rc.6 | not-started external staging evidence |
| Real companion contracts | Files 00/02/09/17/18/19/20/21/24/25 and conditional File 26/CF-03 | not-started external evidence |
| Real providers | File 19, scanner/storage, native owners | not-started external evidence |
| Security deployment tests | IDOR/BOLA/CSRF/XSS/SQLi/SSRF/replay/race/cache leakage | not-started external staging evidence |
| Accessibility/device | keyboard, screen reader, zoom, reflow, RTL, reduced motion, devices | not-started external staging evidence |
| Performance/resilience | load, soak, queue saturation, provider outage, recovery | not-started external staging evidence |
| Migration | inventory, dry run, reconciliation, cutover, rollback | not-started external staging evidence |
| Backup/restore | database/config/private objects/deletion-ledger reconciliation | not-started external staging evidence |
| Staffing/operations | named roles, coverage, escalation, training, runbooks | not-started external evidence |
| Observation window | staged monitoring and zero unresolved critical/high defects | not-started |
| Founder acceptance | exact commit, package checksum, evidence and residual risk | not-started |

## Mandatory evidence metadata

Every manual artifact must bind exact commit/package checksum, environment, software/provider versions, date/time, tester role, preconditions, expected/actual result, redacted logs/screenshots, defect/fix/retest links and approval scope.

## Release law

`Coded`, `Automated-QA Green`, `Packaged`, `Staging-Accepted`, `Live-Deployed`, and `Operational` remain separate statuses. No missing external evidence is converted into a pass.
