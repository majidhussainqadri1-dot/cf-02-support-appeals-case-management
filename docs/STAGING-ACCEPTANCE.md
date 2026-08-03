# CF-02 Hostinger Staging Acceptance

## Entry conditions

- Exact `1.0.0-rc.2` package checksum is recorded.
- Sanitized production-like backup and isolated restore location exist.
- Required companion/provider versions are inventoried.
- Runtime remains fail-closed until the activation evidence set is complete.
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
- [ ] Supported upgrade preserves records, versions, holds and audit truth.
- [ ] Default uninstall clears workers but preserves canonical data.
- [ ] Repair is bounded, previewed, authorized and non-destructive to companion owners.

### Roles and case journeys

Test: Founder, Administrator, Support Agent, Specialist Agent, Team Lead, Appeal Reviewer, liaison, auditor, ordinary user, guardian/representative, pending, suspended and unauthorized actors.

- [ ] Intake creates one traceable case under replay/concurrency.
- [ ] Changed payload under one idempotency key fails.
- [ ] Assignment obeys queue, language, skill, clearance and capacity.
- [ ] SLA pause/resume requires typed evidence and chronology.
- [ ] Internal/restricted notes never appear in requester projections.
- [ ] Attachments remain quarantined until exact scan/hash/MIME evidence passes.
- [ ] Resolution, closure and reopening obey governed policy.
- [ ] Appeal standing, timeliness, independence, dossier and native implementation reconcile.
- [ ] Native-owner failure/retry/dead-letter/compensation remains observable.

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
- [ ] Rollback preserves post-cutover data according to the rehearsal plan.
- [ ] Alerts contain actionable metadata without sensitive content.

## Exit gate

Staging is accepted only when:

1. every mandatory checkbox has evidence;
2. two fresh review/fix rounds are complete against the deployed candidate;
3. known critical/high defects are zero;
4. medium/low residual risks are explicit, bounded and approved;
5. backup/restore and rollback rehearsals pass;
6. exact package/commit evidence is unchanged;
7. Founder approval names the exact version, SHA and package checksum.
