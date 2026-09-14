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

- Source correction: prepared in this round commit.
- Exact-head CI: pending at the moment this register entry is created; the next review round must not start until the corrected exact head is green.
- Staging/Live: not evaluated by this repository review.
