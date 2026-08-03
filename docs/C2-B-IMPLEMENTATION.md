# Phase C2-B — Intake, Case Thread, Attachments and User Portal

Runtime identity: `0.2.0-alpha.1`.

Status: reviewed pure-domain and contract foundation. The conditional plugin remains dormant. No WordPress routes, database tables, provider transports, file-storage backend, scheduled jobs, emails or production user processing are activated by this phase.

## Implemented foundations

- non-sequential immutable `CF02-*` case identifiers;
- guided category-specific intake validation with language, urgency, impact, accessibility and diagnostics-consent context;
- canonical idempotency encoding, normalized payload fingerprint and replay ledger;
- explicit sender-trust state without treating sender trust as authorization;
- deterministic triage with specialist, human-review and emergency-diversion flags;
- case aggregate with optimistic concurrency and governed state transitions;
- strictly separated requester messages, internal notes and restricted notes;
- message replay detection and payload-collision rejection;
- attachment consent/purpose metadata, quarantine, structured scanner evidence, MIME/hash verification, rejection, redaction and case-bound delivery;
- C4 specialized-vault requirement;
- reversible same-requester merge plan with preview hash and recorded reversal reason;
- resolution codes, native outcome references, blocker/native-command checks, closure notice and reopen-window governance;
- privacy-minimized user-case projection without internal notes or raw staff identifiers.

## Requirements covered at foundation level

- `CF02-FR-001` guided intake;
- `CF02-FR-002` normalized channel identity and idempotency contract only; real adapters remain pending;
- `CF02-FR-003` receipt and expectation contract;
- `CF02-FR-004` reversible deduplication domain law;
- `CF02-FR-006` triage policy;
- `CF02-FR-007` case-workspace aggregate;
- `CF02-FR-009` requester communication model;
- `CF02-FR-010` internal-note isolation;
- `CF02-FR-011` attachment lifecycle and access law;
- `CF02-FR-012` governed resolution, closure and reopen law;
- partial support for `CF02-FR-024`, `CF02-FR-025` and `CF02-FR-032`.

## Deliberately pending

- WordPress persistence schema and migrations;
- File 20/25 mounted user and agent routes;
- File 19 delivery adapters and provider webhooks;
- real malware scanner, private object storage and expiring signed delivery;
- assignment, SLA timers, tasks and collaboration services;
- authorization integration with real File 00 and owner contracts;
- browser/accessibility, load, migration, backup/restore, staging and live acceptance.

The existence of these classes and tests is not evidence that the service is packaged, staging-accepted, deployed or operational.
