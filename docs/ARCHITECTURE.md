# Architecture Constitution

## Canonical ownership

CF-02 owns support intake, cases, categories, priority/severity, queues, assignments, user-visible case communications, strictly separate internal notes, consented attachments, SLA policies/timers/pauses, escalations, appeal dossiers, native-owner implementation tracking, reopening, quality review, satisfaction and case-retention audit.

It does not own authentication, membership, professional verification, clinical records, messages, content/listing moderation, payment/refund ledgers, security incident command, privacy-right workflows, notification transport, shell navigation or visual design.

## Architectural invariants

1. Create, update and delete operations use the canonical owner's command contract.
2. Other modules may not write CF-02 tables directly; CF-02 may not write foreign owner tables directly.
3. Events are past-tense facts, not commands or authorization grants.
4. Every linked result carries the canonical owner identifier and record version.
5. Visibility and authorization are rechecked at click time and action time.
6. Indexes, caches, analytics and UI state are never sources of truth.
7. No fallback may broaden access, change money, publish content, or mutate a native decision.
8. Every mutation must support idempotency, optimistic concurrency, traceability and safe failure.

## Runtime layers

- Experience: `/support`, user cases, appeals, agent workbench and quality surfaces.
- Application: intake, triage, state machines, SLA, assignment, appeals and reconciliation.
- Contracts: versioned commands, queries, events and linked-domain adapters.
- Data: cases, messages, attachments, tasks, assignments, SLA, appeals, links, quality and audit.
- Assurance: native controls plus File 24 evidence; File 24 is not a runtime single point of failure.
