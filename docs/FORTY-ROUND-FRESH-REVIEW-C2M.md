# CF-02 C2-M — Fresh Forty-Round Review, Correction and Retest Register

## Review law

Each numbered round examined the corrected RC6 source independently. Where the round exposed a defect, the defect was corrected before the next round; the same round was then retested by `tests/c2m-forty-fresh-review.php`. Where no additional defect appeared, the relevant invariant was revalidated against the already-corrected source. This register does not convert code evidence into staging, live or operational evidence.

| Round | Review focus | Finding and correction | Retest result |
|---:|---|---|---|
| 01 | Legacy category aliases | Alias could reach inconsistent validation paths; normalization moved before validation. | Pass |
| 02 | Request intake queue | Duplicate queue truth risk; canonical `CategoryRoutingPolicy` made authoritative. | Pass |
| 03 | Triage queue | Triage could diverge from intake; same canonical policy enforced. | Pass |
| 04 | Duplicate resolver | Obsolete private queue map removed. | Pass |
| 05 | Learning access routing | Legacy `learning_billing` confirmed to migrate to free-tier `learning_access` and `learning`. | Pass |
| 06 | Requester priority | Local conditional priority logic replaced by `ServiceEqualityPolicy`. | Pass |
| 07 | Intake identity bounds | Repository now rejects malformed requester/idempotency identities. | Pass |
| 08 | Category defense in depth | Repository now asserts the canonical category independently of callers. | Pass |
| 09 | Priority/severity enums | Repository now rejects invalid priority and severity values. | Pass |
| 10 | Queue overwrite | Repository now overwrites caller queue data with canonical routing. | Pass |
| 11 | Locale/subject validation | Repository now validates both before persistence. | Pass |
| 12 | String `false` coercion | PHP truthiness defect corrected with explicit parser. | Pass |
| 13 | String `true` coercion | Explicit accepted representations defined. | Pass |
| 14 | Ambiguous boolean | Values such as `yes`, arrays and objects now fail closed. | Pass |
| 15 | Required boolean | Missing required boolean now fails instead of defaulting. | Pass |
| 16 | REST boolean inventory | Every request boolean migrated from casts to `ApiInput::boolean`. | Pass |
| 17 | Attachment consent | False-like strings cannot become consent. | Pass |
| 18 | Appeal eligibility | Eligibility is explicit and required. | Pass |
| 19 | Locale grammar | Bounded BCP-47-style validation added. | Pass |
| 20 | CRLF before sanitization | Raw line breaks are rejected before WordPress can erase evidence of injection. | Pass |
| 21 | Provider references | Strict opaque-reference grammar prevents whitespace/sanitization collisions. | Pass |
| 22 | Upload-header CRLF | Header-value injection blocked. | Pass |
| 23 | Forbidden headers | Host, Cookie, Content-Length, `Sec-*` and `Proxy-*` authority overrides blocked. | Pass |
| 24 | Valid upload headers | Legitimate `Content-Type` and `X-Amz-*` headers remain supported. | Pass |
| 25 | Action purpose integrity | Semantics-changing `sanitize_key` transformation removed; malformed purpose rejected. | Pass |
| 26 | Repair assertion validity | Expired/suspended contexts now fail at permission boundary. | Pass |
| 27 | Repair step-up | Recent authentication enforced at both boundary and service. | Pass |
| 28 | Repair service validity | Service independently rechecks current context validity. | Pass |
| 29 | Repair serialization | A serialization failure can no longer produce a success claim. | Pass |
| 30 | Repair ledger write | Failed immutable evidence write becomes observable and blocks completion. | Pass |
| 31 | Provider body limits | JSON object-only and 256 KiB limit enforced. | Pass |
| 32 | HMAC context binding | Purpose, key, method and route are cryptographically bound with body/timestamp. | Pass |
| 33 | Provider error disclosure | Raw exception text removed from public response. | Pass |
| 34 | Private failure evidence | Opaque trace and private diagnostic action retained. | Pass |
| 35 | Provider category/queue | Category normalized; provider-supplied queue authority removed. | Pass |
| 36 | Provider priority/severity | Provider-supplied privilege values removed; policy derives priority and normal intake severity. | Pass |
| 37 | Delivery URL | HTTPS and WordPress URL validation required. | Pass |
| 38 | Delivery expiry | Signed delivery grant bounded to five minutes. | Pass |
| 39 | Release truth | RC6 remains candidate; external gates remain pending. | Pass |
| 40 | Register and completion boundary | Forty rounds verified; no staging/live/operational claim introduced. | Pass |

## Consolidated result

The fresh cycle corrected the identified routing, validation, coercion, repair-audit, provider-signature, error-disclosure and secure-delivery defects and added permanent regressions. No known Critical or High code-level defect from this C2-M scope remains open after the final retest. Absolute infallibility is not claimed; new evidence reopens review.

- Hostinger staging: pending.
- Real companion/provider acceptance: pending.
- Manual accessibility/device testing: pending.
- Deployed security and load/soak testing: pending.
- Backup/restore and rollback rehearsal: pending.
- Live deployment: not performed.
- Operational acceptance: not claimed.
