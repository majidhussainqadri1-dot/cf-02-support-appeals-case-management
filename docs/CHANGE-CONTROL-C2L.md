# CF-02 C2-L Latest-Two-Plans Change-Control Record

| Field | Value |
|---|---|
| ID | `CF02-CCR-0003` |
| Trigger | Founder instruction to complete all remaining coding in one integrated batch against the newly rewritten central master plan and CF-02 plan |
| Previous candidate | `1.0.0-rc.5`, schema `1.3.0`, contract `1.1.0` |
| New candidate | `1.0.0-rc.6`, schema `1.3.0`, contract `1.1.0` |
| Governing requirements | CF02-CEN-01…10; CF02-NJ-01…06; relevant AJ-09/10/18/20/24/25/34/35/36/38/39/40; central CV ownership/DoD rules |
| Data impact | No schema change. Guest pre-intake is encrypted client-held continuation data until authenticated case creation. Monthly parity consumes aggregate cohorts only. |
| Security/privacy | Expands privilege-signal rejection; adds encrypted short-lived guest continuation; preserves no-secret public repository; adds delivery-truth and aggregate-only parity controls. |
| Native ownership | No foreign direct writes. Identity, notification, moderation, clinical, financial and security native owners remain authoritative. |
| Migration | None required for schema 1.3.0; supported package upgrade still requires staging evidence. |
| Rollback | Revert C2-L candidate; rc.5 data remains schema compatible. Disable guest endpoints/parity hook by rolling back exact artifact. |
| Tests | C2-L implementation + first corrective review + fresh adversarial second review + full prior regression + release/security/package workflows |
| External gates | Real companions/providers, Hostinger staging, browser/accessibility, independent security/load, restore/rollback, staffing/observation and Founder exact-artifact acceptance remain separate. |
