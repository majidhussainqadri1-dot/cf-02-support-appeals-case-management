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


## Round 03 — Signed provider replay and one-time attachment-delivery audit

### Frozen defect ledger

No new defect was proven. Inbound adapters bind source owner + external event ID + payload hash; scan/redaction callbacks are state-checked and event-idempotent; native terminal results reject changed replay; attachment bearer tokens are random, short-lived and atomically marked used before secure delivery. Existing exact-head evidence from Round 02 remained green because Round 03 made no runtime-code change.

### Correction after ledger freeze

No correction was required.

### Evidence state

- Existing corrected source remained unchanged.
- Staging/Live: not evaluated by this repository review.

## Round 04 — Staff field-level attachment authorization audit

### Frozen defect ledger

1. **C4/C5 attachment metadata overexposure** — `OperationsRepository::caseProjection()` correctly hid restricted message visibility from ordinary assigned staff, but returned every attachment row to any assigned staff actor. That exposed sensitive attachment existence/purpose/classification and associated metadata even when the actor lacked `case.sensitive.read` and recent step-up authentication. This contradicted the CF-02 purpose-bound C4/C5 access law and CEN-06 minimum/JIT sensitive-access rule.

### Correction after ledger freeze

- Staff projections now remove all C4/C5 attachment rows unless the principal has explicit `case.sensitive.read` **and** recent authentication.
- Requester behavior remains unchanged and no raw attachment binary/provider payload is introduced.
- Permanent regression coverage is added to `tests/c2n-fresh40.php`.

### Evidence state

- Source correction applied after the Round 04 ledger freeze.
- Exact-head automated QA required before Round 05 begins.
- Staging/Live: not evaluated by this repository review.


## Round 05 — Case lifecycle, terminal-state content mutation and optimistic-concurrency audit

### Frozen defect ledger

1. **Withdrawn-case reply contradiction** — reply-options marked `withdrawn` non-replyable, but `appendMessage()` blocked only `closed`, allowing a capable caller to append after withdrawal without governed reopen.
2. **Terminal-case attachment contradiction** — reply-options marked `closed` and `withdrawn` non-attachable, but `createAttachment()` had no case-state guard, allowing attachment work on terminal cases without governed reopen.

The remainder of this audit found optimistic case mutations version-bound and governed transition checks present on lifecycle mutations.

### Correction after ledger freeze

- `appendMessage()` rejects both `closed` and `withdrawn` until governed reopen.
- `createAttachment()` applies the same terminal-state boundary.
- Permanent regression coverage added to `tests/c2n-fresh40.php`.

### Evidence state

- Local regression required before commit.
- Exact-head automated QA required before Round 06.
- Staging/Live: not evaluated by this repository review.


## Round 06 — Native-owner link authorization and sensitive linked-object visibility audit

### Frozen defect ledger

1. **Unverified requester-supplied native reference** — intake accepted `affected_object` identifiers and versions supplied by the requester and persisted a CF-02 link without canonical-owner authorization. That could create a false association to another domain object.
2. **C4/C5 linked-object metadata overexposure** — assigned staff could receive sensitive linked-object metadata without `case.sensitive.read` plus recent step-up authentication.

### Correction after ledger freeze

- Intake now requires an explicit `cf02_authorize_native_object_link` canonical-owner decision and an exact verified object version before linking.
- Privacy class and safe projection are taken from canonical-owner authorization evidence rather than requester assertions.
- C4/C5 linked-object rows are hidden from staff without sensitive capability and recent authentication in both the workbench and linked-domain projection.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 07.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 07 — SLA pause/restart semantics and reasoned-event audit

### Frozen defect ledger

1. **Wrong-actor SLA restart** — every case message, including internal notes and staff replies, called `resumeSla()`. A `waiting_user` clock could therefore restart without a requester response.
2. **Provider wait never authoritatively restarted** — native-owner terminal command results did not restart a `waiting_provider` timer.
3. **SLA pause/restart event gap** — pause/restart state changes lacked explicit reasoned SLA events required by CF02-CEN-07.

### Correction after ledger freeze

- `resumeSla()` is now reason-bound and returns whether a matching paused timer actually resumed.
- Only requester/authorized-representative requester-facing messages may resume `waiting_user`.
- Terminal authoritative native-owner results may resume `waiting_provider`; uncertain/retry states do not.
- `SupportSlaPaused` and `SupportSlaResumed` events record reason/evidence references.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 08.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 08 — Appeal reviewer independence and decision-authority audit

### Frozen defect ledger

1. **Organizational-separation runtime gap** — runtime reviewer conflict checks did not require facts proving separation from the original decision actor and organizational unit, despite the plan requiring both capability-wise and organizational independence.
2. **Assigned-reviewer decision gap** — `recordAppealDecision()` did not itself require the current actor to be the assigned reviewer; a narrowly crafted principal with decision capability could bypass reviewer assignment semantics.

### Correction after ledger freeze

- Reviewer-facts contracts now fail closed unless they include original decision actor plus both reviewer/original organizational units.
- Eligibility rejects the original decision actor and same-unit reviewers in addition to prior involvement/conflict/competence/availability rules.
- Only the assigned independent reviewer may record the appeal decision; no implicit override path exists.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 09.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 09 — Attachment delivery token, expiry and sensitive-access audit

### Frozen defect ledger

1. **Expired attachment token issuance** — token issuance checked attachment state but did not enforce the attachment's own `expires_at`, so an otherwise available row could remain downloadable after its retention window.
2. **Sensitive attachment token bypass** — assigned staff could request a bearer token for a C4/C5 attachment without the sensitive capability + recent-auth checks used by case projections.
3. **Attachment expiry not rechecked on token consumption** — token consumption validated the short-lived token but not the underlying attachment expiry.

### Correction after ledger freeze

- Token issuance now rejects expired/missing-expiry attachment rows.
- Staff access to C4/C5 attachments requires `case.sensitive.read` plus recent authentication before token issuance.
- Token consumption now atomically requires both token validity and underlying attachment validity.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 10.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 10 — Guest intake, continuation-token and disclosure-boundary audit

### Frozen defect ledger

No new repository defect was proven. The guest surface remains limited to low-sensitivity categories, rejects prohibited secrets, classifies emergency text before continuation, encrypts continuation state with `sodium_crypto_secretbox`, bounds token lifetime to at most 15 minutes, and requires an authenticated `case.create` principal before case creation. Cross-requester idempotency also fails closed after first redemption. The REST response envelope is private/no-store.

### Correction after ledger freeze

No correction was required. Permanent regression assertions were added to `tests/c2n-fresh40.php` so these boundaries remain explicit.

### Evidence state

- Local syntax/regression required before Round 11.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 11 — Emergency runbook coverage across authenticated intake audit

### Frozen defect ledger

1. **Authenticated-intake emergency coverage gap** — authenticated case creation used a narrow hardcoded danger regex. Account takeover, child safety, privacy breach and financial fraud could therefore enter the ordinary support/SLA path even though CF02-CEN-05 requires distinct emergency runbooks for all six governed emergency classes.

### Correction after ledger freeze

- Authenticated intake now uses the canonical `EmergencyRunbookRegistry` used by guest intake.
- All six emergency classes are detected before ordinary case creation/SLA setup.
- A private diagnostic diversion hook records runbook type, canonical owner, safe mode and trace ID without logging the report body.
- The public API returns a dedicated safe emergency-diversion error code/message instead of silently creating an ordinary support case.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 12.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 12 — Outcome-delivery failure and false-resolve/auto-close audit

### Frozen defect ledger

1. **Dead-letter false-resolved state** — a resolution notice could exhaust delivery retries and dead-letter while the case remained `resolved`, contradicting CF02-CEN-09.
2. **Client-asserted auto-close notice** — `closeCase()` trusted the request boolean `eligible_auto_close_notice_sent` rather than authoritative outbox/File-19 delivery evidence.

### Correction after ledger freeze

- Dead-letter failure of the canonical `support_case_resolved` notice now reopens a still-resolved case and records a reasoned `SupportCaseReopened` worker event tied to the failed delivery.
- Auto-close now queries canonical outbox delivery state and requires `sent`; client booleans no longer authorize closure.
- Explicit user-confirmed closure remains distinct from automated closure.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 13.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 13 — Native-owner command authorization and idempotency-binding audit

### Frozen defect ledger

1. **Command authorization boundary gap** — CF-02 validated only the canonical native-owner key before queueing a native command; it did not require the native owner to authorize the requested action/object/version for the current case actor.
2. **Incomplete idempotency binding** — native command replay comparison omitted `case_uuid` and `expected_native_version`. The same idempotency key could therefore replay an existing command even when case/version semantics differed.

### Correction after ledger freeze

- Native commands now fail closed unless `cf02_authorize_native_owner_command` returns authoritative approval with a verified native version at least as current as the requested expected version.
- Idempotency replay now binds case, native owner, action, object reference, expected native version and payload hash.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 14.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 14 — Retention, holds, appeal finality and purge-safety audit

### Frozen defect ledger

1. **Unresolved-appeal purge gap** — `purgeCase()` blocked an explicit active hold, but did not independently check the canonical appeal lifecycle. A closed case with an appeal still in `submitted`, `eligibility_review`, `accepted`, `under_review`, `native_decision_pending`, `decided`, `implemented`, `rejected` or `reopened` could therefore be permanently purged if a separate hold had not been created or had drifted. That could erase the case, appeal and dossier before due process reached the explicit `closed` appeal state.

The remainder of the retention audit confirmed that canonical case purge is limited to closed cases selected by the retention schedule, external deletion reconciliation must report all required targets reconciled, and active legal/appeal holds already fail closed.

### Correction after ledger freeze

- `purgeCase()` now independently counts appeals for the case and refuses purge while any appeal state is not explicitly `closed`.
- The appeal-finality check executes before provider/cache/search deletion reconciliation and before any destructive transaction begins.
- Permanent regression coverage was added to `tests/c2n-fresh40.php`.

### Evidence state

- Local syntax/regression required before Round 15.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.


## Round 15 — Donor parity, fairness transparency and privacy-threshold audit

### Frozen defect ledger

1. **Parity privacy-threshold drift** — `OperationsTransparencyIntelligence::transparencyCenter()` accepted a caller-specified publication privacy threshold, but invoked `SupportParityAudit::evaluate()` without forwarding that threshold. The parity sub-audit therefore silently fell back to its default cohort minimum of 20. For example, a transparency surface configured for a 50-person privacy threshold could still classify donor/non-donor parity cohorts of only 20–49 people instead of suppressing that sub-audit. This contradicted the Future24 requirement that fairness metrics remain privacy-thresholded and that low-volume cohort disclosure be suppressed.

The remainder of the parity audit found the approved metric allowlist, aggregate-only snapshot enforcement, donor/rank privilege-signal rejection, calendar-month runner and material-variance release-blocker path intact.

### Correction after ledger freeze

- `transparencyCenter()` now forwards its effective `privacyThreshold` into `SupportParityAudit::evaluate()`.
- Parity sub-cohorts below the same publication privacy threshold now return `suppressed` rather than being evaluated at an unintended lower default.
- Permanent behavioral regression coverage was added to `tests/c2n-fresh40.php` using a 50-person publication threshold with 30-person donor/non-donor cohorts.

### Evidence state

- Full repository syntax/regression/security validation required after this final Round 15 coding change.
- Exact-head automated QA required before release/merge.
- Staging/Live: not evaluated by this repository review.
