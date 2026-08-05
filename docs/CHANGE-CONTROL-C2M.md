# Change Control C2-M — Fresh Forty-Round Review and Corrective Hardening

## Governing basis

This change applies a new forty-round review/fix cycle to the exact RC6 source that began at SHA `2e799cd4a1f6a2e5fbc1f60daef7348abe67f038`. It is governed by the Definitive Master Plan v3.0, Consolidated All-Chats Directive Register v2.1 and CF-02 Master Plan v1.0.

## Corrective scope

- Removed a duplicate, stale category-to-queue resolver and made requester intake, triage and provider intake use the canonical routing policy.
- Enforced central repository validation for category, queue, priority, severity, locale, subject, requester identity and idempotency identity.
- Replaced unsafe PHP boolean coercion at external REST boundaries with explicit fail-closed parsing.
- Added bounded locale, single-line, reference and provider-header validation, including CRLF and forbidden-header defenses.
- Hardened purpose validation so malformed input is rejected rather than silently transformed.
- Revalidated repair authorization at request and service layers and made audit-ledger persistence a mandatory success condition.
- Bound provider HMACs to purpose, key ID, HTTP method, REST route, timestamp and exact raw body; bounded JSON bodies and removed public exception leakage.
- Prevented provider-controlled queue, priority and severity authority and validated short-lived HTTPS attachment delivery grants.

## Compatibility and migration

The provider HMAC envelope is intentionally stricter. No production provider is approved yet, so there is no live cutover. Every future provider must implement `docs/PROVIDER-SIGNATURE-CONTRACT.md` and pass staging replay, wrong-route, wrong-purpose, wrong-key, changed-body and clock-skew tests before activation.

## Rollback

Rollback is source-level until staging acceptance. Reverting the C2-M commit restores the prior RC6 candidate; it must not be used after a real provider signs the C2-M contract without a coordinated provider rollback.

## Truthful status

- Specified: complete for this corrective delta.
- Coded: local corrected candidate pending exact GitHub commit and CI.
- Packaged / automated QA: must be regenerated on the new exact head.
- Hostinger staging: pending.
- Live deployment: not performed.
- Operational acceptance: not claimed.
