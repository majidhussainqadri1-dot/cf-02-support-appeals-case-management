# CF-02 Review and Correction Register

## C2-A Round 1 — Requirements, ownership and configuration review

Status: corrected and retested.

1. Expanded mandatory owner contracts to Files 09, 17, 18 and 21.
2. Replaced boolean activation claims with structured evidence.
3. Added category, queue, skill and role validation.
4. Added change-control and Founder-approval binding.

## C2-A Round 2 — Fresh least-privilege review

Status: corrected and exact-head CI passed.

1. Reordered malformed-value validation before duplicate checks.
2. Split Account/Verification and Privacy/Safety queues.
3. Cross-checked measured trigger thresholds.
4. Rejected blank staffing assignments and owner/capability spoofing.

## C2-B Round 1 — Aggregate, replay and disclosure review

Status: corrected and GitHub Actions run `30824537388` passed on PHP 8.1–8.4.

1. Exact requester identity is preserved in idempotency keys.
2. Payment-card detection uses Luhn validation to reduce false positives.
3. Reusing a message idempotency key with a different payload now fails.
4. Attachment identifiers cannot be rebound to different content.
5. Requester projections exclude quarantined and requester-hidden attachment IDs.
6. Direct `Resolved`/`Closed` transitions are blocked; governed resolution policy is mandatory.
7. Receipts show sender trust explicitly without granting authority.

## C2-B Round 2 — Fresh adversarial lifecycle and evidence review

Status: corrected; exact final-head CI required and recorded separately.

1. Replaced delimiter-joined idempotency input with canonical JSON encoding.
2. Added an intake replay ledger: exact replay returns one case; changed payload under the same key fails.
3. Added portable description-length handling and canonical intake fingerprints.
4. Made emergency/acute diversion an explicit triage result.
5. Prevented direct `Scanned` state changes; quarantine release now requires structured scanner name/version, matching SHA-256, MIME verification and verdict.
6. Scanner errors preserve quarantine; infection or MIME mismatch produces rejection.
7. Blocked direct `Reopened` transitions; governed reopen enforces the resolution window.
8. Closed cases are immutable until governed reopen succeeds.
9. User portal no longer emits raw support-agent references.
10. Resolution content rejects duplicate actions and secrets.
11. Merge reversal reason is retained for audit.
12. Added adversarial tests for delimiter collisions, scan spoofing, replay mismatch, expired reopening, emergency diversion and portal minimization.

## Truthful completion state

These reviews prove repository-level foundation behavior only. WordPress persistence, real providers, scanner/storage services, routes, staging, migration, backup/restore, accessibility, live deployment and operations remain unproved. Any new evidence reopens review.
