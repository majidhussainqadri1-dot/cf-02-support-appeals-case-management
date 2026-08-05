# C2-L — Three-Plan Final Code-Completion Change Control

| Field | Decision |
|---|---|
| Governing sources | Definitive Master Plan v3.0; All-Chats Directive Register v2.1; CF-02 Master Plan v1.0 |
| Previous candidate | `1.0.0-rc.5` |
| New candidate | `1.0.0-rc.6`; schema `1.3.0`; contract `1.1.0` |
| Defects corrected | stale RC2 release/staging identities; hard-coded workflow versions; missing documentation identity regression; non-machine-verifiable three-plan traceability |
| Data impact | none; no schema or canonical support data mutation |
| Security/privacy effect | prevents wrong-artifact staging and false completion; preserves external gates as pending |
| Migration | no data migration; package identity and documentation replacement only |
| Rollback | revert C2-L commit and invalidate RC6 artifact; RC5 remains historical and must not be promoted after the defect discovery |
| Tests | C2-L implementation, Review 1, fresh adversarial Review 2, full prior matrix, identity and traceability verifiers |
| Status | code candidate only; staging/live/operational evidence remains separate |
