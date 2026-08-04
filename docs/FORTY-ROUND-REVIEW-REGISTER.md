# CF-02 Forty-Round Review → Fix Register

**Scope:** Definitive Master Plan v3.0 + CF-02 plan v1.0
**Rule:** every numbered review ended with a concrete correction and a permanent regression check.
**Runtime claim:** code/automated candidate only; staging, live and operational acceptance remain separate.

| Round | Lens | Defect found | Correction made | Regression evidence |
|---:|---|---|---|---|
| 01 | Activation lifecycle | Staging validation was circularly blocked by production acceptance evidence. | Split core staging evidence from production-only external gates. | `tests/run.php; tests/c2j-forty-review.php #01` |
| 02 | Exact artifact binding | Approval fields were not compared with an authoritative deployed identity. | Added exact runtime identity provider and field-by-field approval binding. | `#02` |
| 03 | Hash formats | Source SHA and package SHA-256 accepted either length. | Require 40-char source SHA and 64-char package digest. | `#03` |
| 04 | Dependency freshness | Contract health depended on wall-clock time and missed future evidence. | Inject deterministic clock; reject stale and future checks. | `#04` |
| 05 | Conditional dependencies | Optional finance/clinical dependencies lacked explicit activation semantics. | Require them only when their enabled condition is true. | `#05` |
| 06 | File 00 fail-closed | Any permissive identity fallback would create alternate authority. | Retained null default and require verified audience-bound File 00 assertion. | `#06` |
| 07 | Assertion abuse bounds | Roles/capabilities/representation arrays were not explicitly bounded. | Added count, format and reference constraints. | `#07` |
| 08 | Duplicate controllers | Obsolete REST/admin/frontend classes retained permissive code in the package. | Removed the three legacy classes from source and package. | `#08` |
| 09 | Legacy roles | Deleting duplicate roles lacked migration evidence. | Record assignment counts and pseudonymous set hashes before removal. | `#09` |
| 10 | Route schema parity | Route envelopes advertised schema 1.2 instead of canonical 1.3. | Publish SchemaCompletion::VERSION. | `#10` |
| 11 | Route receipt integrity | Repair checked only weak receipt presence. | Validate exact route set, owner, runtime, schema and contract versions. | `#11` |
| 12 | Route normalization | Encoded/repeated-slash private paths could evade header classification. | Normalize decoded paths and sanitize admin page input. | `#12` |
| 13 | Browser hardening | Private response headers were incomplete. | Added no-store, anti-index, frame, permissions and CSP controls. | `#13` |
| 14 | Managed key scope | Key evidence lacked explicit audience/runtime health binding. | Require healthy, audience-scoped, exact-runtime key evidence. | `#14` |
| 15 | Key lifecycle | Keys lacked active/retired/revoked state. | Added explicit lifecycle and revoked-key denial. | `#15` |
| 16 | Key uniqueness | Duplicate material under multiple IDs was possible. | Reject duplicate fingerprints and multiple active keys. | `#16` |
| 17 | Rotation continuity | Retired-key decrypt/new-key rewrite needed proof. | Added bounded retained-key decryption and rotation regression. | `#17` |
| 18 | Rotation batch | Limit was applied per table, multiplying work. | Apply one global batch budget and emit item-failure evidence. | `#18` |
| 19 | Cursor encoding | Unpadded base64url decoding was unstable. | Restore padding and verify round trip. | `#19` |
| 20 | Cursor scope | Cursor payload/position/expiry validation was weak. | Bound length, fields, TTL, issue time, scope and signature. | `#20` |
| 21 | Representation cursor | Cursor scope omitted represented-requester membership. | Sort and bind requester set into scope hash. | `#21` |
| 22 | Error disclosure | Runtime exception text was returned to clients. | Return safe public taxonomy; forward private trace evidence. | `#22` |
| 23 | Pagination constitution | Legacy offset paths remained callable. | Disable legacy list methods and use opaque keyset overlay only. | `#23` |
| 24 | Requester priority | Requester input could self-select P1/P2. | Derive preliminary priority from bounded impact/urgency; triage retains authority. | `#24` |
| 25 | Mutation replay | Mutations needed systematic key/version review. | Confirmed explicit idempotency and optimistic-version guards across commands. | `#25` |
| 26 | Attachment consent | Attachment creation did not explicitly require consent. | Require consent before metadata/quarantine creation. | `#26` |
| 27 | Upload grant safety | Provider upload URL/expiry/header evidence was insufficiently validated. | Require valid HTTPS URL, safe headers and <=15-minute expiry. | `#27` |
| 28 | SLA observability | SLA regression needed explicit event evidence. | Retained timer processing and AtRisk/Breached events. | `#28` |
| 29 | Delegated access | Collaborator expiry/restricted approval required fresh verification. | Regressed time expiry and specialist approval. | `#29` |
| 30 | Appeal evidence input | Appeal references were unbounded and unsanitized. | Added bounded, unique, secret-safe list normalization. | `#30` |
| 31 | Feedback opt-out | REST path encrypted a comment even when user opted out. | Drop rating and comment at boundary when opted out. | `#31` |
| 32 | Reviewer independence | Appeal assignment fairness required explicit regression. | Regressed involvement, conflict, competence, availability and clearance. | `#32` |
| 33 | Native reconciliation | Implementation confirmation needed full owner/action/object/version matching. | Regressed every native command dimension. | `#33` |
| 34 | Metrics privacy | Low-volume feedback could expose individuals. | Regressed minimum-cohort suppression. | `#34` |
| 35 | Retention holds | Destructive retention needed hold-first evidence. | Regressed active hold blocks purge. | `#35` |
| 36 | Repair authority | Read capability and browser-minted IDs could authorize repair. | Require repair.execute, recent auth and owner-provided approval evidence. | `#36` |
| 37 | Schema completion | Repair/key rotation evidence had no canonical tables/version. | Promoted schema 1.3 with repair and rotation ledgers. | `#37` |
| 38 | Lifecycle cleanup | Key rotation scheduler was omitted from uninstall cleanup. | Clear all six workers on deactivation/uninstall. | `#38` |
| 39 | Operational UI | User/admin journeys lacked linked fields/deep links and repair governance. | Added guided fields, deep-link hydration, consent and operator-supplied approval. | `#39` |
| 40 | Release truth | Metadata still claimed complete rc.3/schema 1.2. | Bumped rc.4/schema 1.3 and retained external acceptance as separate. | `#40` |

## Closure law

A round is closed only when its correction is present in source and its regression passes. A new defect reopens the relevant round; this register never converts external staging/provider evidence into code evidence.
