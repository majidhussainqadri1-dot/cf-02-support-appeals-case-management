# CF-02 — Future Support, Appeals & Case Intelligence 24

## Status and boundary

This C2-M addendum codes the foundations for `CF02-FUT-001` through `CF02-FUT-024`. The code is feature-gated, side-effect free at the domain layer, and does not itself activate a staging/live feature. Existing CF-02 activation, authorization, native-owner, privacy/security, migration, rollback, staging, Founder-approval and live-verification laws remain controlling.

No database schema fork or public contract fork is introduced by this batch: schema remains `1.3.0`; contract remains `1.1.0`. Any later persistent storage, public REST endpoint, provider binding, mobile app, co-browsing transport, voice transport, translation provider, scanner/storage provider, webhook consumer, or status notification transport requires its own owner-approved design and external acceptance evidence.

## Future24 implementation matrix

| ID | Facility | Coded foundation | Permanent guardrail |
|---|---|---|---|
| CF02-FUT-001 | Hidayah Support Copilot | `Future\AssistanceIntelligence::supportCopilot()` | Suggestion/editable draft only; no final/native/clinical/safety decision |
| CF02-FUT-002 | Pre-Ticket Self-Service Resolver | `AssistanceIntelligence::selfServiceResolution()` | Emergency/sensitive paths divert to governed intake; case creation stays available |
| CF02-FUT-003 | Case Risk Radar | `AssistanceIntelligence::caseRiskRadar()` | Service-risk signals only; donor/popularity/rank/payment privilege rejected |
| CF02-FUT-004 | Next-Best-Action Engine | `AssistanceIntelligence::nextBestAction()` | Recommends; never self-executes; native mutations remain owner commands |
| CF02-FUT-005 | Known Problem & Root-Cause Center | `ProblemKnowledgeIntelligence::problemFingerprint()` | Deterministic problem linkage; individual cases remain separate/openable |
| CF02-FUT-006 | Knowledge-Gap Detector | `ProblemKnowledgeIntelligence::knowledgeGaps()` | Privacy threshold; human approval/versioning required before publication |
| CF02-FUT-007 | Appeal Eligibility Preview | `AppealIntelligence::eligibilityPreview()` | Non-binding preview; governed appeal workflow remains authority |
| CF02-FUT-008 | Appeal Evidence Room | `AppealIntelligence::evidenceRoom()` | Typed owner/type/ref/version/hash only; native truth is not copied |
| CF02-FUT-009 | Decision Difference Viewer | `AppealIntelligence::decisionDifference()` | Sensitive material redacted; reasoned original/appeal difference only |
| CF02-FUT-010 | Support-Service Complaint / Second-Level Review | `AppealIntelligence::serviceComplaint()` | Same handler cannot be final reviewer; conflict-free independence required |
| CF02-FUT-011 | Secure Co-Browsing | `EvidenceChannelIntelligence::coBrowsingSession()` | Explicit consent, scoped selectors, expiry; no credential capture/remote control |
| CF02-FUT-012 | Consent-Based Voice Support | `EvidenceChannelIntelligence::voiceSupportHandoff()` | File 17 remains communication owner; CF-02 stores linkage/evidence reference only |
| CF02-FUT-013 | Client-Side Evidence Sanitizer | `EvidenceChannelIntelligence::sanitizeEvidenceEnvelope()` | Nonessential metadata stripped; secrets/payment material warned/blocked |
| CF02-FUT-014 | Resumable Large Evidence Upload | `EvidenceChannelIntelligence::resumableUpload()` | Contiguous chunks, hashes, final checksum and scan gate before access |
| CF02-FUT-015 | Public Service Status Center | `ContinuityExperienceIntelligence::publicStatus()` | Public-safe fields only; case/user/private fields prohibited |
| CF02-FUT-016 | Incident Subscription | `ContinuityExperienceIntelligence::incidentSubscription()` | Incident-scoped hashed destination; unrelated reporters/cases invisible |
| CF02-FUT-017 | Smart Multilingual Support | `ContinuityExperienceIntelligence::translationDraft()` | Draft only; high-risk clinical/legal/safety/financial/identity text human-reviewed |
| CF02-FUT-018 | Accessibility Support Profile | `ContinuityExperienceIntelligence::accessibilityProfile()` | Support preferences only; identity/representative authority stays native |
| CF02-FUT-019 | Low-Bandwidth / PWA Case Mode | `ContinuityExperienceIntelligence::lowBandwidthDraft()` | C4/C5 data cannot enter uncontrolled offline cache |
| CF02-FUT-020 | Native Mobile Support | `ContinuityExperienceIntelligence::mobileEnvelope()` | Same CF-02 backend is canonical; no duplicate mobile case database |
| CF02-FUT-021 | Secure Deep Links & QR | `IntegrationSecurityIntelligence::issueSecureDeepLink()` / `verifySecureDeepLink()` | Signed short-lived opaque token; local path only; no raw case ID/open redirect |
| CF02-FUT-022 | Institutional Support API + Webhooks | `IntegrationSecurityIntelligence::institutionalApiPolicy()` | Scoped access, idempotency, nonce, body hash, HMAC signature, replay/rotation law |
| CF02-FUT-023 | Agent Training & Simulation Lab | `OperationsTransparencyIntelligence::trainingScenario()` | Explicitly synthetic fixtures only; real user/patient/payment/identity/message data forbidden |
| CF02-FUT-024 | Transparency & Fairness Center | `OperationsTransparencyIntelligence::transparencyCenter()` | Privacy-thresholded aggregate metrics, donor parity, no covert staff scoring |

## Implementation evidence

- Registry: `src/Future/FeatureCatalog.php` — 24 stable IDs, owners, activation state and guardrails.
- Functional implementation suite: `tests/c2m-future24.php`.
- Corrective/adversarial review 1: `tests/c2m-review1.php`.
- Fresh independent review 2: `tests/c2m-review2.php`.
- All future domain files are prohibited from direct WordPress persistence/network side effects by Review 2.
- C2-M tests are permanent PHP 8.1–8.4 CI gates and also run through `composer test` before deterministic release packaging.

## Truthful completion boundary

C2-M means **repository-coded and reviewed foundation** only after exact-head CI is green. It does not mean the 24 facilities are already provider-connected, UI-complete, Staging-Accepted, Live-Deployed or Operational. Those statuses require the normal environment-specific evidence chain.
