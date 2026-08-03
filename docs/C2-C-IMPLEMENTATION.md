# Phase C2-C — Assignment, SLA, Escalation, Queue Health and Incident Foundation

Status: pure-domain and contract foundation at runtime identity `0.3.0-alpha.1`. This phase does not create WordPress tables, routes, cron jobs, notifications or provider connections.

## Accountable assignment

Every case receives at most one accountable owner. Eligibility is constrained by queue, skills, role, on-duty state, capacity and sensitive clearance. Preferred language contributes to deterministic routing but does not act as authorization. An unassignable case remains explicitly unassigned and must escalate; the router never chooses an ineligible agent.

Transfers require a new eligible decision, a reason and optimistic concurrency. The former owner loses owner access immediately. Collaboration grants are scoped, expiring and independently revocable.

## SLA policy and calendar law

SLA policies are versioned by queue and priority. They define first-response, update and resolution targets, warning thresholds, calendar references and enumerated pause reasons. Coverage calendars calculate working minutes in a declared timezone, weekly windows and holidays.

The first-response clock never pauses. Update and resolution clocks may pause only for an evidenced requester wait, native-owner dependency or approved incident dependency. Backdating, arbitrary staffing pauses, silent reset and stale-version mutation are rejected.

## Breach prediction and escalation

Prediction compares remaining working time with estimated queue delay and required work. It produces `normal`, `watch`, `high` or `breach` evidence. Escalation remains human-governed and may require watch, team-lead, specialist or incident-command action. P1 cases cannot be silently downgraded.

## Queue health

Queue health uses minimized aggregate metrics only: open, unassigned, P1-unassigned, at-risk, breached, oldest age, agent availability, capacity and assigned load. It does not include case bodies, identities, clinical data or private evidence.

## Major incident linkage

Incident linkage preserves each case as an independent record. It never merges cases, shares another requester's identity, pauses SLA automatically, closes a case or authorizes a native-domain action. The case projection contains only the incident's public status and case-specific continuation instruction.

## Remaining runtime work

Persistence, transaction boundaries, WordPress/REST routes, notification delivery, scheduler workers, real calendars, staffing-source synchronization, search projections, dashboards, observability, migration, staging and rollback evidence remain future work.
