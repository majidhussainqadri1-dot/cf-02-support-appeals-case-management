# CF-02 Deployed Security and Privacy Test Plan

## Evidence boundary

Repository unit/adversarial tests are necessary but do not replace deployed-environment testing. This plan requires exact-package evidence on an isolated staging environment.

## Identity and authorization

- Anonymous, authenticated, pending, suspended, guardian, user, assigned agent, unassigned agent, specialist, lead, reviewer, liaison, auditor and administrator cases.
- Object-level and field-level IDOR/BOLA across cases, messages, attachments, tasks, SLA evidence, appeals, dossiers, exports and audit projections.
- Capability forgery, stale role/capability cache, account suspension after page load, expired recent-auth and cross-queue/cross-case access.
- Unauthorized responses must avoid record-existence, count, queue, attachment, reviewer and internal-state leakage.

## Request and mutation integrity

- CSRF/nonces, same-site behavior, method confusion and content-type confusion.
- Idempotency replay with same and changed payloads.
- Concurrent intake, message, assignment, SLA, appeal decision, command and configuration mutations.
- Stale record versions, duplicated callbacks, reordered events and delayed provider outcomes.
- Rate-limit key collision, proxy/IP spoof assumptions, challenge bypass and emergency-direction preservation.

## Injection and rendering

- Stored/reflected XSS in subjects, messages, notes, filenames, translations, templates, findings and external references.
- SQL injection in filters, cursors, identifiers, pagination and admin diagnostics.
- SSRF in provider/object references, callback URLs, evidence links and export destinations.
- Path traversal, dangerous filenames, MIME mismatch, polyglot/archive/decompression-bomb and malicious attachment corpus.
- CSV/formula injection and unsafe content-disposition in exports.

## Secrets and sensitive data

- OTPs, passwords, private keys, access tokens, payment-card data and provider secrets rejected from ordinary intake/messages/exports.
- No secret-bearing values in browser responses, HTML source, REST errors, logs, traces, metrics labels, cache keys, search documents or GitHub artifacts.
- Encrypted message tamper detection and key-unavailable fail-closed behavior.
- Private/restricted routes use no-store/noindex and safe referrer policy.

## Appeal fairness and native ownership

- Self-review, prior-involvement, conflict, missing competence/clearance and expired assignment rejection.
- Late exception requires explicit accessibility, representation or new-evidence basis.
- CF-02 cannot directly change identity, clinical, content moderation, payment, message-report or privacy-owner truth.
- Native command outcome/version drift blocks closure and remains observable.

## Privacy lifecycle

- Purpose limitation, minimum field projections and restricted evidence approvals.
- Export authorization, bounded contents, manifest hashes and expiry.
- Erasure/deletion with retention/legal/appeal holds, backup expiry and downstream reconciliation.
- Low-volume feedback/quality metrics suppress identity and do not expose case content.

## Availability and recovery

- Database/cache/cron/provider/File 24/File 19/companion outage behavior.
- Queue saturation, dead letter, retry storms and bounded worker deadlines.
- Backup restore, key recovery, deletion-ledger replay and rollback around new writes.
- Assurance `Unknown` must never be rendered as `Secure` or `Passed`.

## Exit criteria

- Every test has exact version, actor, object/state, expected/actual result and evidence.
- Critical/high findings are corrected and freshly retested.
- Medium/low residual risks require bounded, time-limited approval.
- A fresh adversarial review runs after all corrections.
- The final result is tied to the exact package SHA-256 and staging environment.
