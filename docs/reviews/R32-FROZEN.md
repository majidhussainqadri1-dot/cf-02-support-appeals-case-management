# R32 — Attachment bearer-token delivery atomicity review

Review scope: exact R31-corrected commit `0e071e977ad619b4c57a6d523f792e74d331f99c`.

This review was completed before any R32 application-code correction.

## Frozen finding

1. **R32-01 — A one-time attachment token is consumed before secure delivery is proven available.** `ProviderWebhookController::consumeAttachment()` calls `OperationsRepository::consumeAttachmentToken()` first; that method sets `used_at`, and only afterwards does the controller invoke `cf02_attachment_secure_delivery`. If the secure-delivery adapter is unavailable/errors, the bearer token has already been irreversibly consumed even though no delivery was produced. This creates a false-consumption/availability failure and encourages unsafe token re-issuance.

## Frozen correction plan

- Serialize each bearer-token delivery attempt with a connection-scoped token lease.
- Inspect/validate an unused token without consuming it.
- Invoke secure delivery while holding the lease.
- Mark the token used only after the delivery adapter returns an authorized delivery result.
- Preserve one-time replay rejection and expiry checks.
- Add R32 regression coverage and run PHP lint, full Composer tests, security scan, then exact-branch verification before R33.
