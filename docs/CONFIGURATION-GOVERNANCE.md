# CF-02 Configuration Governance

Status: C2-K three-plan harmonized coded candidate. Operational runtime remains fail-closed until activation and external acceptance gates pass.

## Canonical categories

| Key | Queue | Data class | Native decision owner | Specialist-only |
|---|---|---:|---|---|
| `account_access` | `account` | C3 | Files 00/02 | No |
| `verification` | `verification` | C3 | File 09 | No |
| `publishing` | `publishing` | C2 | Files 21/22/23 | No |
| `learning_access` | `learning` | C2 | File 05 / File 00 access assertions | No |
| `clinic_appointment` | `clinic` | C3 | File 08 | No |
| `messages_calls` | `communications` | C3 | File 17 | No |
| `media_pdf` | `media` | C2 | Files 10/11/12 | No |
| `marketplace` | `marketplace` | C3 | File 18 | No |
| `privacy_data_rights` | `privacy_liaison` | C4 | File 24 / native privacy owner | Yes |
| `safety_abuse` | `safety_liaison` | C4 | Relevant native safety owner | Yes |
| `accessibility` | `technical` | C2 | Files 20/25 | No |
| `technical` | `technical` | C2 | Platform operations | No |
| `institutional_governance` | `governance_liaison` | C4 | Founder/File 24/native owner | Yes |

The taxonomy only classifies and routes support work. It never grants CF-02 authority to approve identity, verification, moderation, clinical, payment, privacy-right or marketplace decisions.

## Queue baseline

Each queue declares stable categories, required skills, an ownership role, a coverage-schedule reference and an escalation role. A category must belong to exactly one queue. Missing categories, unknown skills, duplicate assignments and queue/category drift are configuration defects.

Account access and verification use separate queues because their native authorities differ. Privacy-right and safety/abuse cases also use separate liaison queues; a generic sensitive queue would create unjustified cross-purpose visibility. Sensitive work requires case-level purpose, approved assignment, expiring access and audited specialist handling.

## Staffing roles

- `support_agent`: assigned-case minimum data, replies and tasks.
- `specialist_agent`: product-domain diagnosis and native-owner action requests.
- `team_lead`: assignment, SLA escalation, bounded bulk actions and quality coaching.
- `appeal_reviewer`: independent dossier review and reasoned outcome.
- `sensitive_liaison`: controlled privacy, security, safety or clinical escalation.
- `auditor`: read-only sampled evidence and minimized metrics.

High-risk assignment must separate requester, original decision maker, reviewer, executor, reconciler and auditor. A reviewer cannot review their own decision or execute the resulting native-domain action.

## Version and release law

Categories, forms, queues, skills, templates, SLA policies, escalation rules and feature flags must be versioned. Every material change requires:

1. preview and validation;
2. dated change-control record;
3. affected files and requirement IDs;
4. privacy, security and Sharīʿah impact;
5. migration and rollback plan;
6. automated and staging tests;
7. Founder approval where the change is strategic or high risk.

Invalid configuration never falls back to broader access or automatic resolution. It remains inactive and produces a diagnostic result.

