# CF-02 C2-K Change-Control Record

| Field | Value |
|---|---|
| ID | `CF02-CCR-0002` |
| Requested by | Founder directive and three-plan forensic review |
| Date | 2026-08-05 |
| Old rule | RC4 reconciled the central and CF-02 plans but preceded the final All-Chats v2.1 directives and retained paid/billing terminology, no File 26 aggregate contract and incomplete due-process/frontend harmonization. |
| New rule | RC5 reconciles the central plan v3.0, All-Chats v2.1 and CF-02 v1.0 with free learning access, donation non-privilege, Islamic institutional due process, privacy-safe File 26 projection, canonical queue routing and packaged localized frontend assets. |
| Affected files | `CF-02`, `File 00`, `File 19`, `File 20`, `File 24`, `File 25`, `File 26`, conditional `CF-03` |
| Requirement/directive IDs | `CF02-FR-001`, `006`, `008`, `018`–`025`, `027`, `029`, `030`, `032`; `CHAT-BIZ-022`; `CHAT-GOV-023`; `CHAT-QA-001` |
| Data impact | No schema change. Legacy category input is normalized from `learning_billing` to `learning_access`; new output projections contain aggregates only. |
| Security/privacy impact | Reduces privilege manipulation, cross-purpose queue selection and raw case leakage; adds threshold suppression and native implementation verification. |
| Sharīʿah/institutional impact | Codifies due process and protects good-faith inquiry while preserving Founder-approved Islamic institutional governance. |
| Migration | Accept old category key at boundaries, persist canonical key, rebuild configuration projection, publish contract `1.1.0`, and verify queue/config parity. |
| Rollback | Revert the RC5 commit/branch. Do not downgrade persisted canonical categories without an explicit compatibility adapter. Keep File 26 projection disabled by default. |
| Tests | C2-K implementation suite, Review 1, fresh adversarial Review 2, all prior C2-A–C2-J tests, package verification, security scan and exact-head GitHub Actions. |
| Approval state | Implementation authorized by the user's instruction to begin the next appropriate step; merge/staging/live approval remains separate. |
