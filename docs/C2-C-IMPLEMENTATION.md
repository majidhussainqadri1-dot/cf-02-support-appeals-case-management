# Phase C2-C — Assignment, SLA, Escalation, Queue Health and Incident Foundation

Status: reviewed pure-domain and contract foundation at runtime identity `0.3.0-alpha.1`. This phase does not create WordPress tables, routes, cron jobs, notifications or provider connections.

## Accountable assignment

Every case receives at most one accountable owner. Eligibility is constrained by queue, exact supported language, required skills, role, on-duty state, capacity and sensitive clearance. An unassignable case remains explicitly unassigned and must escalate; the router never chooses an ineligible candidate.

Assignment decisions are deterministic but short-lived. They expire after five minutes and must be committed with an explicit timestamp, preventing stale capacity data from granting ownership. Transfers require a fresh eligible decision for the same case and queue, a reason and optimistic concurrency. The former owner loses owner access immediately.

Collaboration grants are scoped, expiring, independently revocable and immutable until revoked. Restricted projections require an explicit purpose-bound approval in addition to the scope name.

## SLA policy and calendar law

SLA policies are versioned by queue and priority. They define first-response, update and resolution targets, warning thresholds, calendar references and enumerated pause reasons. Coverage calendars calculate working minutes in a declared timezone, non-overlapping weekly windows and validated real holiday dates.

The first-response clock never pauses. Update and resolution clocks may pause only after first response and only for an evidenced requester wait, native-owner dependency or approved incident dependency. An existing breach cannot be hidden by starting a pause.

First response and update require unique `message:` evidence; native waits require `command:` evidence; approved incident waits require `incident:` evidence; resolution requires unique `resolution:` evidence. Evidence cannot be consumed by two clock mutations. Backdating, silent reset, duplicate evidence, stale-version mutation and resolution without first response are rejected.

## Breach prediction and escalation

Prediction compares remaining working time with estimated queue delay and required work. It produces `normal`, `watch`, `high` or `breach` evidence. An observation older than the current clock state is rejected. A governed paused clock remains on watch until resume recalculates its deadlines; a completed clock does not generate a false active breach.

Escalation remains human-governed and may require watch, team-lead, specialist or incident-command action. P1 cases cannot be silently downgraded.

## Queue health

Queue health uses minimized aggregate metrics only: open, unassigned, P1-unassigned, at-risk, breached, oldest age, agent availability, capacity and assigned load. Subset counts, capacity and empty-queue metrics are cross-validated. Stale or materially future-dated snapshots fail closed and cannot justify a healthy or assignment decision.

No case bodies, identities, clinical data or private evidence are placed in queue-health snapshots.

## Major incident linkage

Incident linkage preserves every case as an independent record. It never merges cases, shares another requester's identity, pauses SLA automatically, closes a case or authorizes a native-domain action. Links are service-bound, versioned and chronological.

Public incident summaries and resolution text reject prohibited secrets. A case projection contains the public incident state and continuation instruction, but never another case identity, internal actor details or an internal notice reference.

## Two review and correction rounds

Round 1 corrected language fallback, malformed candidates, restricted-owner leakage, mutable collaborator grants, breach-masking pauses, response-free resolution, inconsistent queue metrics and nondeterministic incident chronology.

Fresh adversarial Round 2 corrected stale assignment reuse, cross-queue transfer, unapproved restricted collaboration, SLA evidence replay, overlapping calendars, impossible holidays, stale/future queue snapshots, stale breach observations and internal incident notice leakage.

The code-focused adversarial run `30844984327` passed on head `4267d5bb407a651a65208d175265e3818e3334ae`. After harmonizing the review, traceability and change-control records, GitHub Actions run `30845180019` passed on final exact head `7042623aaf26924162f63334bb5745d2af137515` for PHP 8.1–8.4, including all prior suites and both C2-C review suites.

## Remaining runtime work

Persistence, transaction boundaries, WordPress/REST routes, notification delivery, scheduler workers, real calendar and staffing sources, search projections, dashboards, observability, migration, staging, backup/restore and rollback evidence remain future work.
