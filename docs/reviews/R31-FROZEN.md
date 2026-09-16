# R31 — Runtime resolution and terminal-state error-boundary review

Review scope: exact R30-corrected repository commit `df0b4a8e50249d2f0ace40900efe37a9f491b191`.

This review was completed before any R31 application-code correction.

## Frozen findings

1. **R31-01 — Missing runtime class import in native-command worker.** `RuntimeWorker::processCommands()` calls `SupportCaseId::fromString()` but the file does not import `Sabri\CF02\Domain\SupportCaseId`. On a terminal native-owner result this resolves to the wrong namespace and can throw at runtime.
2. **R31-02 — Native-command terminal result can be regressed to retry/dead-letter by a later post-result failure.** The broad `catch (Throwable)` surrounds dispatch, authoritative result persistence, event recording, and SLA resumption. If any step after `updateCommandResult(... succeeded/failed ...)` throws, the catch calls `updateCommandResult(... retry/dead_letter ...)`, so a terminal result is not monotonic.
3. **R31-03 — Event publication can be regressed after successful publication.** `processEvents()` marks an event published and then calls `do_action('cf02_domain_event_published', ...)` inside the same retry catch. An observer exception can therefore call `markEventRetry()` after publication and cause duplicate external publication.
4. **R31-04 — Repository command-result mutation does not enforce terminal immutability.** `updateCommandResult()` accepts any allowed target state without validating the stored current state, so `succeeded`, `failed`, or `dead_letter` can be moved back to a non-terminal state by an error path or stale caller.

## Frozen correction plan

- Import the canonical `SupportCaseId` class.
- Separate native-owner dispatch/result persistence from post-result projection/event/SLA work so post-result failures cannot rewrite authoritative terminal state.
- Make command-result transitions monotonic in the repository; terminal states are immutable/idempotent.
- Treat post-publish observer failures as observer failures, never as publication failure; harden event state updates against published-state regression.
- Add R31 regression checks, then run PHP lint, complete Composer test suite, security scan, and exact-branch CI before R32 begins.
