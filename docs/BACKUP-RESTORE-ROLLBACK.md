# CF-02 Backup, Restore and Rollback Runbook

## Scope

The rehearsal must cover CF-02-owned database tables/options, encrypted message material, attachment/provider references, configuration versions, audit evidence, migration mappings, legal/appeal holds, scheduler state and downstream owner reconciliation. It must not mutate or claim ownership of companion-module data.

## Backup prerequisites

- Record exact application, schema and contract versions.
- Record database charset/collation/time zone and row counts per CF-02 table.
- Capture configuration and activation evidence separately from secrets.
- Verify private-object/provider inventory and checksums where CF-02 owns references.
- Export pending outbox, command, retention and reconciliation work states.
- Preserve key-recovery procedure privately; never place key material in this repository.
- Record holds and deletion obligations that must survive restore.

## Restore rehearsal

1. Provision an isolated staging target.
2. Restore the database and approved private-object/configuration evidence.
3. Verify schema identity before enabling any worker.
4. Run integrity counts and sampled hash comparisons.
5. Keep runtime fail-closed while companion contracts are checked.
6. Reconcile cases, messages, attachments, assignments, SLA timers, appeals, dossiers, commands, outbox, tasks, quality, feedback, holds, configuration, migration and audit records.
7. Reapply deletion/erasure tombstones and legal/appeal holds.
8. Reconcile native-owner command outcomes and downstream delivery status.
9. Execute read-only smoke tests, then bounded write tests with disposable records.
10. Record achieved RPO/RTO, discrepancies, corrections and retest evidence.

## Rollback rehearsal

- Define the exact pre-change snapshot and candidate SHA/package checksum.
- Freeze or queue writes during the final cutover delta according to the migration plan.
- Preserve new post-cutover data through reverse mapping, compensation or an approved read-only window.
- Revert plugin package and compatible schema/configuration state.
- Clear/rebuild only derivative caches and schedules.
- Verify no foreign-owner data was deleted or rewritten.
- Reconcile provider callbacks, outbox deliveries and native-owner commands that crossed the rollback boundary.
- Run requester, agent, appeal and auditor smoke journeys.
- Keep a rollback decision log with actor, reason, timestamps, evidence and final state.

## Mandatory failure injections

- database restore delay or partial failure;
- missing/incorrect encryption-key recovery evidence;
- corrupted row or attachment checksum;
- provider/object unavailable;
- duplicate or late callback;
- worker restart during retry;
- stale companion contract;
- legal/appeal hold conflict;
- deletion already propagated downstream;
- cache/cron unavailable;
- rollback after new cases were created.

## Pass criteria

- No unexplained case, SLA, appeal, command, hold or audit divergence.
- No sensitive content appears in logs, screenshots or public caches.
- Native-owner truth remains authoritative.
- RPO/RTO meet the approved targets.
- New data created around cutover is accounted for.
- Every discrepancy has a defect, correction and successful retest.
- Founder and operational owners approve the exact rehearsal evidence.
