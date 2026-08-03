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

Status: corrected and exact-head CI passed.

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

## C2-C Round 1 — Assignment, SLA integrity and incident chronology review

Status: corrected and GitHub Actions run `30844311641` passed on PHP 8.1–8.4.

1. Language changed from a soft routing preference to an exact eligibility constraint; unsupported language remains explicitly unassigned.
2. Malformed assignment candidate entries are rejected rather than silently ignored.
3. Ordinary accountable owners cannot access restricted projections.
4. Existing collaboration grants cannot be silently widened or extended; revocation is required before material change.
5. SLA pause is prohibited before first response and after an existing breach.
6. SLA resolution requires a recorded first response.
7. Queue-health capacity, empty-queue and subset metrics are cross-validated.
8. Major-incident audit mutations use explicit chronological timestamps rather than hidden wall-clock time.

## C2-C Round 2 — Fresh adversarial authority, time and evidence review

Status: corrected; GitHub Actions run `30844984327` passed on exact head `4267d5bb407a651a65208d175265e3818e3334ae` for PHP 8.1–8.4.

1. Assignment decisions now expire after five minutes so stale capacity snapshots cannot be committed indefinitely.
2. Assignment commit time is explicit and expired decisions fail closed.
3. Transfers cannot use a decision issued for another queue.
4. Restricted collaborator scope requires explicit purpose-bound approval.
5. First response, updates and resolution require typed, unique evidence references; one item cannot manipulate multiple SLA events.
6. Breach prediction rejects observations older than the current clock and treats governed pauses as watch rather than false normality.
7. Coverage calendars reject overlapping working windows and impossible holiday dates.
8. Queue-health evaluation rejects stale and materially future-dated snapshots.
9. Public incident summaries and resolutions reject prohibited secrets.
10. Public incident projection exposes only notice availability, never the internal notice reference.
11. Adversarial tests cover expired assignment, cross-queue transfer, restricted collaboration, SLA evidence replay, calendar corruption, stale metrics and public incident leakage.

## Truthful completion state

These reviews prove repository-level pure-domain and contract foundations only. WordPress persistence, transaction boundaries, public/agent routes, scheduler workers, notification delivery, real staffing/calendar providers, scanner/storage services, dashboards, staging, migration, backup/restore, accessibility, live deployment and operations remain unproved. Any new defect, dependency drift, staging evidence or security/privacy finding reopens review.
