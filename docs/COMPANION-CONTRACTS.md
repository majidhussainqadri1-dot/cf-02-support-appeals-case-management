# CF-02 Companion Contract Manifests

CF-02 orchestrates support and appeals; native owners retain their own decisions. This document reflects the **canonical runtime registry** at `src/Contracts/RequiredCompanionContracts.php` for candidate `1.0.0-rc.7` (contract `1.3.0`). It does not by itself establish that a dependency is deployed or healthy.

| Manifest key | Expected owner | Required capability | Activation gate |
|---|---|---|---|
| `file_00_membership_contract` | File 00 | `identity_assertions` | Mandatory |
| `file_02_authentication_contract` | File 02 | `account_recovery_commands` | Mandatory |
| `file_09_verification_contract` | File 09 | `verification_decision_reference` | Mandatory |
| `file_17_message_report_contract` | File 17 | `message_report_decision_reference` | Mandatory |
| `file_18_marketplace_case_contract` | File 18 | `listing_decision_reference` | Mandatory |
| `file_19_notification_contract` | File 19 | `notification_delivery_requests` | Mandatory |
| `file_20_route_shell_contract` | File 20 | `route_shell_mount` | Mandatory |
| `file_21_content_case_contract` | File 21 | `content_decision_reference` | Mandatory |
| `file_24_assurance_manifest` | File 24 | `assurance_manifest` | Mandatory |
| `file_25_component_contract` | File 25 | `component_manifest` | Mandatory |
| `file_26_ranking_contract` | File 26 | `privacy_safe_case_outcome_projection` | `enabled: true` (ranking_outcome_projection_enabled) |
| `attachment_storage_provider` | Approved Attachment Storage | `private_quarantine_storage` | Mandatory |
| `malware_scanner_provider` | Approved Malware Scanner | `malware_scan_verdict` | Mandatory |
| `email_adapter_provider` | Approved Email Adapter | `signed_inbound_email` | Mandatory |
| `managed_key_provider` | Approved Key Provider | `versioned_keyring_rotation` | Mandatory |
| `cf_03_financial_contract` | CF-03/provider | `financial_action_request` | `enabled: true` (financial_flows_enabled) |
| `clinical_safety_owner_contract` | File 08/CF-01 | `clinical_safety_diversion_reference` | `enabled: true` (clinical_diversion_enabled) |

## Required evidence for every applicable manifest

The canonical runtime validator requires **all** of the following, not just an installed or enabled plugin:

```php
[
    'ready' => true,
    'owner' => 'Exact registered owner',
    'contract_version' => '1.3.0', // illustrative; each provider declares its own valid version
    'capabilities' => ['exact_registered_capability'],
    'health' => 'healthy',
    'health_checked_at' => '2026-10-21T00:00:00+05:00', // illustrative; MUST be fresh at evaluation
]
```

- Contract versions must be explicitly versioned; each provider may have a different compatible version. The `1.3.0` above is **not** a requirement that every provider uses CF-02's version.
- Health evidence must be no older than 15 minutes at evaluation and cannot be more than 60 seconds in the future.
- A conditional contract is enforced when its `enabled` field is exactly `true`; omitting it is permitted only while that capability remains disabled.
- Real provider attestations, operational readiness and Founder approval are external evidence, **not** repository claims.
- CF-02 does not write directly to companion tables/meta; native owner commands must be reconciled before cross-domain closure.
- Missing, unhealthy or stale dependencies fail closed; they never grant additional authority.
