# CF-02 Complete Coding and Packaged Candidate — 1.0.0-rc.2

## Governing result

The repository contains the complete code-level candidate for the approved CF-02 master plan across C2-A through C2-H. Each phase owns a foundation suite plus two independent review/fix suites. The full matrix runs on PHP 8.1, 8.2, 8.3 and 8.4.

The repository also contains a deterministic WordPress packaging and release-evidence foundation. This advances the truthful state from coded candidate to **packaged candidate**, not to Hostinger staging acceptance or live deployment.

## Implemented domains

1. Fail-closed activation, companion contracts, taxonomy, queues, staffing and configuration constitution.
2. Guided intake, canonical replay control, case/thread/attachment lifecycle, receipts, deduplication, resolution, closure and reopen.
3. One accountable owner, skill/capacity/language routing, collaboration, transfer, SLA clocks/calendars, breach prediction, escalation, queue health and major-incident linkage.
4. Purpose-bound access, recent authentication, native-owner commands, retry/compensation/reconciliation, authorized search, secure export, legal hold and tamper-evident audit.
5. Appeal standing/timeliness/exception policy, independent reviewer assignment, immutable dossier, reasoned decisions, native implementation and reopening.
6. Quality review, knowledge suggestions, optional feedback, configuration stage/approval/rollback, automation guardrails, progressive rate limits, task lifecycle, degraded delivery and retention/deletion planning.
7. Immutable migration mapping, strict dual-read reconciliation, zero-divergence cutover and rollback evidence.
8. WordPress schema v1.1.0, idempotent installer, transactional intake replay, encrypted messages, requester REST APIs, scheduler hooks, accessible support/appeal/admin shells, private cache/index headers, load budgets, recovery evidence, training readiness and release gates.
9. Deterministic package allowlist, package manifest, per-file SHA-256, SPDX SBOM, provenance statement, source/package verifier, repository safety scan and artifact workflow.
10. Clean WordPress lifecycle smoke for package install, fail-closed activation, deactivate/reactivate and non-destructive uninstall preservation.

## Review doctrine executed

C2-A through C2-H retain two independent review/fix rounds per phase. The release-engineering layer then completed two fresh review/fix rounds covering:

- exact PR head versus synthetic merge SHA;
- artifact/source identity;
- archive ordering and reproducibility;
- package version/schema/readme parity;
- non-destructive uninstall law;
- scanner false positives;
- fail-closed `pending` versus stable `dormant` lifecycle semantics;
- clean WordPress package activation/reactivation/uninstall behavior.

Every discovered defect was corrected, retested and retained as regression evidence.

## Candidate identity

- Plugin candidate: `1.0.0-rc.2`
- Schema: `1.1.0`
- Plan: `1.0`
- Package slug: `cf-02-support-appeals-case-management`
- Branch: `agent/cf-02-foundation-c2-a`
- Pull request: Draft PR #1

## Automated package evidence

For each exact candidate head, the release workflow produces:

- `cf-02-support-appeals-case-management-1.0.0-rc.2.zip`;
- package-contained manifest with source SHA and per-file hashes;
- SPDX 2.3 SBOM;
- provenance statement;
- `SHA256SUMS`;
- a GitHub Actions artifact bound to the exact branch head.

The provenance statement is not a cryptographic signature or independent certification. Formal artifact signing remains a separately approved private operational process.

## Truthful acceptance boundary

`1.0.0-rc.2` means **complete coded and deterministically packaged release candidate**. It does not mean staging accepted, live or operational. The module remains fail-closed. These gates still require real external evidence:

- exact companion-owner adapters and contract tests;
- malware scanner, private object storage, email/notification and other providers;
- named staffing, calendars, training and on-call coverage;
- Hostinger fresh install, supported upgrade, concurrent activation, repair and production-like migration;
- browser, keyboard, screen reader, zoom, reflow, RTL/LTR, reduced-motion and device testing;
- deployed penetration, IDOR/BOLA/CSRF/XSS/SQLi/SSRF/replay/race/cache-leak testing;
- load, soak, queue saturation, provider outage and recovery objectives;
- backup/restore, deletion-ledger reapplication and downstream reconciliation;
- rollback rehearsal protecting cutover-time and post-cutover records;
- observation window, zero unresolved critical/high defects and Founder approval for the exact commit and package.

Until these pass, `main`, Hostinger staging and live remain unchanged and the Draft PR must not be represented as operational deployment.
