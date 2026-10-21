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

Versioned owner-and-capability manifests are mandatory for **14** currently registered dependencies: `file_00_membership_contract`, `file_02_authentication_contract`, `file_09_verification_contract`, `file_17_message_report_contract`, `file_18_marketplace_case_contract`, `file_19_notification_contract`, `file_20_route_shell_contract`, `file_21_content_case_contract`, `file_24_assurance_manifest`, `file_25_component_contract`, `attachment_storage_provider`, `malware_scanner_provider`, `email_adapter_provider`, `managed_key_provider`. Conditional dependencies (`file_26_ranking_contract`, `cf_03_financial_contract`, `clinical_safety_owner_contract`) must also be ready when explicitly enabled. Every applicable manifest requires `ready: true`, exact owner and capability, a valid explicit version, `health: healthy` and fresh `health_checked_at` (not older than 15 minutes or more than 60 seconds in the future). Plugin presence and activation hooks alone never satisfy these checks. Canonical registry: `src/Contracts/RequiredCompanionContracts.php`.

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
