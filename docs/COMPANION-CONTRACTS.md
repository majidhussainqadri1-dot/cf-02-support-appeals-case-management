# CF-02 Companion Contract Manifests

CF-02 is an orchestrator, not a substitute decision authority. Runtime activation requires explicit, versioned, owner-signed readiness evidence from every mandatory companion domain.

| Manifest key | Expected owner | Required capability |
|---|---|---|
| `file_00_membership_contract` | File 00 | `identity_assertions` |
| `file_09_verification_contract` | File 09 | `verification_decision_reference` |
| `file_17_message_report_contract` | File 17 | `message_report_decision_reference` |
| `file_18_marketplace_case_contract` | File 18 | `listing_decision_reference` |
| `file_20_route_shell_contract` | File 20 | `route_shell_mount` |
| `file_21_content_case_contract` | File 21 | `content_decision_reference` |
| `file_24_assurance_manifest` | File 24 | `assurance_manifest` |
| `file_25_component_contract` | File 25 | `component_manifest` |

Each manifest must contain:

```php
[
    'ready' => true,
    'owner' => 'File NN',
    'contract_version' => 'x.y.z',
    'capabilities' => ['declared_capability'],
]
```

## Contract law

- Plugin presence, active hooks, class names or tables are not readiness evidence.
- `latest`, empty or unversioned contracts are rejected.
- Owner mismatch is rejected.
- Missing declared capability is rejected.
- CF-02 never writes directly to a companion table or meta store.
- A native owner command result must be reconciled before a cross-domain case or appeal is closed.
- Dependency outage leaves the affected action pending, degraded or escalated; it never broadens authority.
