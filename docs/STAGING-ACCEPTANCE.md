# CF-02 Hostinger Staging Acceptance

## Entry conditions

- Exact `1.0.0-rc.6` package checksum is recorded.
- Exact repository commit and package manifest/SBOM/provenance are recorded.
- Sanitized production-like backup and isolated restore location exist.
- Required companion/provider versions are inventoried.
- Runtime remains fail-closed until activation evidence is complete.
- Named tester roles and emergency rollback authority are available.

## Environment record

| Field | Evidence |
|---|---|
| Environment | pending |
| WordPress version | pending |
| PHP version/extensions | pending |
| Database/version/charset | pending |
| Cache/CDN/object storage | pending |
| Candidate commit | pending |
| Candidate ZIP SHA-256 | pending |
| Backup ID and restore proof | pending |
| Test window | pending |

## Acceptance matrix

### Lifecycle

- [ ] Fresh install creates no unauthorized foreign-owner data.
- [ ] Activation records pending state and remains dormant without gates.
- [ ] Concurrent activation/installer lock cannot create drift or duplicate schema.
- [ ] Deactivate/reactivate is idempotent.
- [ ] Supported upgrade from an accepted prior package/schema preserves records, versions, holds and audit truth.
- [ ] Default uninstall clears workers but preserves canonical data.
- [ ] Repair is bounded, previewed, authorized and non-destructive to companion owners.

### Core case/appeal journeys

- [ ] CF02-NJ-01 technical case end to end.
- [ ] CF02-NJ-02 account-access escalation through native owner.
- [ ] CF02-NJ-03 independent moderation appeal and native implementation.
- [ ] CF02-NJ-04 privacy case with minimum disclosure/hold/rights path.
- [ ] CF02-NJ-05 emergency diversion without ordinary SLA/false promise.
- [ ] CF02-NJ-06 provider/queue outage, backlog recovery, duplicate suppression and SLA correction.
- [ ] Safe guest pre-intake creates no case before authenticated step-up and sensitive categories are denied.
- [ ] File 19 outcome-delivery failure cannot become false resolved/auto-closed state.
- [ ] Monthly donor/non-donor parity audit consumes aggregate cohorts only and blocks unexplained material variance.

### Security and privacy

- [ ] IDOR/BOLA object and field tests deny unauthorized access without existence leakage.
- [ ] CSRF, nonce misuse, stale capability, suspension and recent-auth tests fail closed.
- [ ] SQLi/XSS/SSRF/path/MIME/polyglot/zip-bomb/secret-content cases are blocked.
- [ ] Replay, race, stale version, duplicate delivery and cache-key tests pass.
- [ ] Private routes are no-store/noindex and do not leak in search, logs or telemetry.
- [ ] Export, retention, deletion and legal/appeal hold cases reconcile.
- [ ] File 24 outage does not disable native enforcement; assurance becomes Unknown.

### Accessibility and visual behavior

- [ ] Keyboard-only flows and visible focus pass.
- [ ] Screen-reader names, errors, status and table semantics pass.
- [ ] 200%/400% zoom, reflow and no horizontal page overflow pass.
- [ ] Urdu/Arabic RTL, English LTR and mixed-direction content pass.
- [ ] Contrast, 44px targets and reduced-motion behavior pass.
- [ ] 320–1920px representative viewport matrix passes.

### Reliability and performance

- [ ] Queue saturation and bounded worker batches preserve critical actions.
- [ ] Provider timeout/outage yields explicit degraded state and safe retry.
- [ ] Cache/cron delay does not grant authority or hide breach evidence.
- [ ] Restore meets approved RTO/RPO and replays deletion/hold obligations.
- [ ] Rollback preserves post-cutover data according to rehearsal plan.
- [ ] Alerts contain actionable metadata without sensitive content.

## Exit gate

Staging is accepted only when every mandatory checkbox has evidence; two fresh review/fix rounds are complete against the deployed candidate; known Critical/High defects are zero; residual risk is explicit/approved; backup/restore and rollback rehearsals pass; exact package/commit evidence remains unchanged; and Founder approval names the exact version, SHA and package checksum.
