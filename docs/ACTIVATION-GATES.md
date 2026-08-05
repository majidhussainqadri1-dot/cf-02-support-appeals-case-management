# Conditional Activation Gates

The repository may contain reviewed code before runtime extraction, but the plugin remains fail-closed until every gate is evidenced. Installation, active hooks, a boolean option or a green unit test is not activation evidence.

## Founder gate

The activation record must contain:

- `approved: true`;
- governing plan version `1.0`;
- canonical `approved_by: founder` identity;
- a valid `CF02-ACT-###` change-control ID;
- an ISO-8601 approval timestamp;
- an explicit runtime switch independent of installation.

## Mandatory companion manifests

Versioned owner-and-capability manifests are required from Files 00, 09, 17, 18, 20, 21, 24 and 25. Every manifest must declare `ready`, exact owner, semantic contract version and the required capability. Hook or plugin presence alone is rejected.

## Structured operational evidence

Every operational gate is an accepted evidence record with an evidence ID, owner, artifact reference and ISO-8601 timestamp:

- measured case-volume/backlog/SLA extraction trigger, including window, metric, threshold and observed value;
- named queue ownership, coverage hours, escalation tree, emergency diversion, privacy training and quality sampling;
- privacy review;
- security review;
- migration/reconciliation plan;
- rollback plan;
- zero open Critical and High defects.

The broader activation dossier must additionally contain data-flow mapping, classification and retention, abuse cases, provider-exit planning, automated unit/contract/security/privacy/accessibility/load/migration evidence, staging install/upgrade, backup/restore and rollback rehearsal.

## Fail-closed behavior

When a gate is missing or malformed:

- no public or agent support routes are registered;
- no support tables are created in Phase C2-A;
- no jobs, emails or provider calls run;
- Site Health reports the denied evidence;
- affected actions do not fall back to broader access;
- the rest of the platform's eligible public reading remains unaffected.
