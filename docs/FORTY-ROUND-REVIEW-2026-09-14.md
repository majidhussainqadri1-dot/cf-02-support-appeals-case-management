# CF-02 Fresh 40-Round Review Register — 2026-09-14

**Governing baseline:** exact repository commit `cf3c727bcc1eb8da42835e52fc1f9a654f6bd640` + latest Central Governing Master Plan + CF-02 Future24 amended plan.

**Mandatory round law:** each numbered round is completed as a read-only audit first. Findings are frozen before any correction begins. All proven defects from that round are then corrected together, regression coverage is added, and exact-head automated evidence is required before the next round starts. Repository, staging and live evidence remain separate.

## Round 01 — Future24 security and integrity audit

### Frozen defect ledger

1. **FUT-021 encoded/backslash local-link bypass** — `IntegrationSecurityIntelligence` accepted a path solely from raw leading-slash checks. Encoded slash/backslash/percent forms could survive signing and later normalize into a network-path/open-redirect form in a downstream decoder. Verification also had a weaker path check than issuance. This violated the Future24 local-only/no-open-redirect law.
2. **FUT-014 repeated-content chunk rejection** — `EvidenceChannelIntelligence::resumableUpload()` rejected two sequential chunks merely because their SHA-256 values were equal. A valid file may contain identical byte ranges, so content-hash uniqueness was incorrectly used as replay protection even though contiguous chunk indexes already establish sequencing.

### Correction after ledger freeze

- FUT-021 now uses one shared local-path validator for issue and verify. It rejects network paths, backslashes/control characters, encoded slash/backslash/colon, and encoded percent signs that could enable double-decoding bypasses.
- FUT-014 now permits identical chunk content at different contiguous indexes and rejects byte totals that exceed the declared upload size.
- Permanent regression coverage was added to `tests/c2m-review1.php` for encoded/double-encoded/backslash redirect forms, repeated-content chunks, overflow, and verification denial.

### Evidence state

- Corrected exact head: `c6dfab906f59e1321c3320d3ac03be44646b6289`.
- Exact-head CF-02 CI run `34839114872`: **SUCCESS**.
- Staging/Live: not evaluated by this repository review.

## Round 02 — Public API/provider error-disclosure and authorization-boundary audit

### Frozen defect ledger

1. **Provider endpoint internal error disclosure** — `ProviderWebhookController::run()` returned the message of any `RuntimeException` directly from public/signed provider endpoints. The messages include internal state and validation detail such as signing-key availability, command existence/state, provider availability, replay reasons, and adapter validation details. This contradicted the existing public-safe error taxonomy used by the canonical REST controller and the CF-02 API constitution requiring safe messages plus trace IDs.

### Correction after ledger freeze

- Provider endpoint failures now return one generic public-safe localized message and trace ID.
- Full exception evidence is emitted only to the private `cf02_provider_request_failed` diagnostic hook with trace ID and error class.
- A new permanent `tests/c2n-fresh40.php` regression register is introduced and wired into Composer and PHP 8.1–8.4 CI. It retains regressions from Round 01 and adds the Round 02 provider-disclosure gate.

### Evidence state

- Source correction: prepared in the Round 02 commit.
- Exact-head CI: pending at the moment this entry is created; Round 03 must not start until the corrected exact head is green.
- Staging/Live: not evaluated by this repository review.
