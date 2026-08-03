# CF-02 Complete Coding Candidate — 1.0.0-rc.1

## Governing result

The repository now contains the complete code-level candidate for the approved CF-02 master plan across C2-A through C2-H. Each phase owns a foundation suite plus two independent review/fix suites. The full matrix runs on PHP 8.1, 8.2, 8.3 and 8.4.

## Implemented domains

1. Fail-closed activation, companion contracts, taxonomy, queues, staffing and configuration constitution.
2. Guided intake, canonical replay control, case/thread/attachment lifecycle, receipts, deduplication, resolution, closure and reopen.
3. One accountable owner, skill/capacity/language routing, collaboration, transfer, SLA clocks/calendars, breach prediction, escalation, queue health and major-incident linkage.
4. Purpose-bound access, recent authentication, native-owner commands, retry/compensation/reconciliation, authorized search, secure export, legal hold and tamper-evident audit.
5. Appeal standing/timeliness/exception policy, independent reviewer assignment, immutable dossier, reasoned decisions, native implementation and reopening.
6. Quality review, knowledge suggestions, optional feedback, configuration stage/approval/rollback, automation guardrails, progressive rate limits, task lifecycle, degraded delivery and retention/deletion planning.
7. Immutable migration mapping, strict dual-read reconciliation, zero-divergence cutover and rollback evidence.
8. WordPress schema v1.1.0, idempotent installer, transactional intake replay, encrypted messages, requester REST APIs, scheduler hooks, accessible support/appeal/admin shells, private cache/index headers, load budgets, recovery evidence, training readiness and release gates.

## Review doctrine executed

For C2-D, C2-E, C2-F, C2-G and C2-H:

- coding was completed;
- Review Round 1 discovered and corrected integrity, completeness, portability and persistence defects;
- the corrected work was retested;
- a fresh adversarial Review Round 2 examined different authority, replay, disclosure, race, fairness, abuse, migration and release-overclaim paths;
- every new defect was corrected and covered by regression tests.

The earlier C2-A, C2-B and C2-C phases retain the same two-round review evidence.

## Runtime identity

- Plugin candidate: `1.0.0-rc.1`
- Schema: `1.1.0`
- Plan: `1.0`
- Branch: `agent/cf-02-foundation-c2-a`
- Pull request: Draft PR #1

## Truthful acceptance boundary

`1.0.0-rc.1` means **complete coded release candidate**, not live operational acceptance. The module remains fail-closed. The following require real external evidence before merge/activation/release acceptance:

- exact companion owner adapters and contract tests;
- malware scanner, private object storage, email/notification and other providers;
- named staffing, calendars, training and on-call coverage;
- WordPress fresh install, concurrent activation, upgrade, repair, deactivate/reactivate and non-destructive uninstall;
- real database migration dry run, dual read, cutover and rollback;
- browser, keyboard, screen reader, zoom, reflow, RTL/LTR, reduced-motion and device testing;
- penetration, IDOR/BOLA/CSRF/XSS/SQLi/SSRF/replay/race/cache-leak tests in a deployed environment;
- load, soak, queue saturation, provider outage and recovery objectives;
- backup/restore, deletion-ledger reapplication and downstream reconciliation;
- package/source parity, SBOM, provenance, secret scan, checksum/signature and release notes;
- staging observation window, zero unresolved critical/high defects and Founder approval for the exact commit and package.

Until these pass, `main`, staging and live remain unchanged and the Draft PR must not be represented as operational deployment.
