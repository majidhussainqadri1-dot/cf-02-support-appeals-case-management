# CF-02 Production Readiness Evidence Register

## Status boundary

Runtime candidate: `1.0.0-rc.3`  
Plan: `1.0`  
Schema: `1.2.0`  
Contract: `1.0.0`

The central-plan and CF-02-plan code surface is complete. This register does not itself prove Hostinger staging, live deployment or operational acceptance.

## Evidence states

Each gate is independent: `not-started`, `in-progress`, `passed`, `failed`, `blocked`, or `Founder-accepted-risk`.

| Gate | Required evidence | Current code/repository state |
|---|---|---|
| Governing traceability | Central plan + CF02-FR-001…034 → code/tests | complete candidate |
| Runtime command/query surface | 33 commands + 20 queries + both namespaces | complete candidate |
| Authorization/ownership | File 00 assertion, native-owner keys, no direct companion writes | complete candidate |
| Persistence/lifecycles | Schema 1.2, replay, events, attachments, SLA, appeals, holds, retention | complete candidate |
| Two fresh coding reviews | C2-I Review 1 and fresh adversarial Review 2 | complete candidate |
| Source identity | Exact branch/head/PR | automated on exact head |
| Deterministic package | ZIP, manifest, SBOM, provenance, checksums, parity | automated on exact head |
| Dormant lifecycle | clean install, fail-closed activation, reactivation, safe uninstall | automated on exact head |
| Active runtime integration | ready activation, schema, routes, intake/replay/persistence | automated controlled environment |
| Supported upgrade | prior accepted package/schema to candidate | not-started |
| Real companion contracts | Files 00/09/17/18/20/21/24/25 | not-started |
| Real providers | File 19, scanner/storage, native owners | not-started |
| Security deployment tests | IDOR/BOLA/CSRF/XSS/SQLi/SSRF/replay/race/cache leakage | not-started |
| Accessibility/device | keyboard, screen reader, zoom, reflow, RTL, reduced motion, devices | not-started |
| Performance/resilience | load, soak, queue saturation, provider outage, recovery | not-started |
| Migration | inventory, dry run, dual read, reconciliation, cutover, rollback | not-started |
| Backup/restore | database/config/private objects/deletion-ledger reconciliation | not-started |
| Staffing/operations | named roles, coverage, escalation, training, runbooks | not-started |
| Observation window | staged monitoring and zero unresolved critical/high defects | not-started |
| Founder acceptance | exact commit, package checksum, evidence and residual risk | not-started |

## Mandatory evidence metadata

Every manual artifact must bind the exact commit and package checksum, environment, software/provider versions, date/time, tester role, preconditions, expected/actual result, redacted logs/screenshots, defect/fix/retest links and approval scope.

## Release law

`Coded`, `Automated-QA Green`, `Packaged`, `Staging-Accepted`, `Live-Deployed` and `Operational` remain separate statuses. No missing external evidence is converted into a pass.
