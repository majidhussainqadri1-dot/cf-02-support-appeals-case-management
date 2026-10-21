# CF-02 C2-S — Fresh forty-item source/plan review and retention fail-closed corrective record

## Source order and exact scope
- **Primary**: CF-02 Support/Appeals/Case Management conditional master plan (Future24 amended 2026-09-08), requirements CF02-FR-001…034, plus 24 separately gated Future24.
- **Governing**: Sabri Social Homeopathy Platform consolidated three central plans, especially single native source of truth, privacy/clinical/financial restrictions, two corrective reviews, and live-first deployed artifact rule.
- **Cross-file source/consumer boundaries**: File 00/02/09 identity/verification; File 17 communication and reports; File 18 marketplace; File 19 notifications; File 20 shell; File 24 assurance; File 25 UI; CF-01 clinical; CF-03 finance; CF-05 analytics conditional owner.
- Earlier full automated traceability suite: `tests/c2o-plan-40-structural-witnesses.php`, plus `tests/crossplan-40-review.php`. Each of the following 40 rows has source-method evidence. **This is 40 scoped review points, not 40 independently repeated full-repository audits.** Green method existence is *not* proof of completed provider/UI/mobile/browser operations.

## Forty plan/code/cross-file review points
| # | Requirement | Topic | Two source witnesses | Current cross-file/acceptance boundary | Proof classification |
|---:|---|---|---|---|---|
| 1 | CF02-FR-001 | Guided intake | `C:createCase / G:issueContinuation` | File 00 identity; emergency diversion | Source-method witness, not full end-to-end |
| 2 | CF02-FR-002 | Omnichannel adapters | `P:inbound / O:recordInboundReceipt` | File 17/19, signed provider; sender binding remains external | Source-method witness, not full end-to-end |
| 3 | CF02-FR-003 | Receipt and expectations | `O:enqueueDelivery / C:createCase` | File 19 delivery acceptance pending | Source-method witness, not full end-to-end |
| 4 | CF02-FR-004 | Deduplication and merge | `O:mergeCases / O:splitMergedCase` | Canonical case owner; replay and merge limits | Source-method witness, not full end-to-end |
| 5 | CF02-FR-005 | Spam, abuse and rate safety | `G:consumeRateLimit / L:decide` | MySQL lock/load proof pending | Source-method witness, not full end-to-end |
| 6 | CF02-FR-006 | Triage policy | `C:triageCase / T:decideAt` | CEN-SAFE; medical emergencies diverted | Source-method witness, not full end-to-end |
| 7 | CF02-FR-007 | Case workspace | `C:workbench / O:caseProjection` | File 00 actor/object/field scopes | Source-method witness, not full end-to-end |
| 8 | CF02-FR-008 | Assignment and collaboration | `C:assignCase / O:assignCase` | Requester/provider not an arbitrary staff role | Source-method witness, not full end-to-end |
| 9 | CF02-FR-009 | User communication | `C:addRequesterMessage / O:appendMessage` | File 17 message authority separated | Source-method witness, not full end-to-end |
| 10 | CF02-FR-010 | Internal notes and mentions | `C:editInternalNote / O:reviseInternalNote` | C4/C5 content purpose and retention | Source-method witness, not full end-to-end |
| 11 | CF02-FR-011 | Attachment handling | `O:createAttachment / O:recordAttachmentScan` | Scanner/storage provider live absent | Source-method witness, not full end-to-end |
| 12 | CF02-FR-012 | Resolution and closure | `C:resolveCase / C:closeCase` | No closure with unresolved native owner action | Source-method witness, not full end-to-end |
| 13 | CF02-FR-013 | SLA policy registry | `O:slaPolicy / O:ensureSlaTimer` | Schedule and staff owner proof pending | Source-method witness, not full end-to-end |
| 14 | CF02-FR-014 | Pause/resume law | `O:pauseSla / O:resumeSla` | Audit/version rules; no silent reset | Source-method witness, not full end-to-end |
| 15 | CF02-FR-015 | Breach prediction and escalation | `W:processSla / O:slaAtRisk` | File 19 deliveries and staffed on-call absent | Source-method witness, not full end-to-end |
| 16 | CF02-FR-016 | Queue health | `O:backlogHealth / C:backlogHealth` | Aggregate-only, no covert employee surveillance | Source-method witness, not full end-to-end |
| 17 | CF02-FR-017 | Major incident linkage | `O:linkMajorIncident / C:linkMajorIncident` | Incident owner and emergency runbook | Source-method witness, not full end-to-end |
| 18 | CF02-FR-018 | Appeal eligibility | `C:appealEligibility / E:decide` | Standing and deadline adverse cases | Source-method witness, not full end-to-end |
| 19 | CF02-FR-019 | Independent reviewer | `C:assignAppealReviewer / V:assign` | Conflict-of-interest and separation of duties | Source-method witness, not full end-to-end |
| 20 | CF02-FR-020 | Appeal dossier | `O:appealDossierData / O:submitAppeal` | Sensitive evidence view/retention | Source-method witness, not full end-to-end |
| 21 | CF02-FR-021 | Reasoned decision | `C:recordAppealDecision / D:projection` | Due process and auditable notice | Source-method witness, not full end-to-end |
| 22 | CF02-FR-022 | Implementation reconciliation | `C:confirmAppealImplementation / I:reconcile` | Only native owner can finalize source-of-truth | Source-method witness, not full end-to-end |
| 23 | CF02-FR-023 | Appeal timeliness and access | `C:submitAppeal / C:reopenAppeal` | Accessible appeal path and deadline | Source-method witness, not full end-to-end |
| 24 | CF02-FR-024 | Purpose-bound access | `A:decide / O:caseForActor` | File 00 verified assertion and C4/C5 step-up | Source-method witness, not full end-to-end |
| 25 | CF02-FR-025 | Redaction and secure evidence | `O:recordAttachmentRedaction / O:issueAttachmentToken` | Real scanner, private token consumption | Source-method witness, not full end-to-end |
| 26 | CF02-FR-026 | Case search | `O:searchAuthorized / C:searchCases` | Authorized rows; SQL failure must not look empty | Source-method witness, not full end-to-end |
| 27 | CF02-FR-027 | Quality review | `O:recordQuality / C:recordQuality` | Donor neutral/appeal fairness | Source-method witness, not full end-to-end |
| 28 | CF02-FR-028 | Knowledge suggestions | `K:suggest / H:isUsable` | Advice not native diagnosis or authority | Source-method witness, not full end-to-end |
| 29 | CF02-FR-029 | Satisfaction and feedback | `O:addFeedback / C:submitFeedback` | Aggregate-only privacy/anti-surveillance | Source-method witness, not full end-to-end |
| 30 | CF02-FR-030 | Configuration governance | `O:stageConfiguration / O:rollbackConfiguration` | Founder change control & approval | Source-method witness, not full end-to-end |
| 31 | CF02-FR-031 | Case export and legal hold | `X:build / O:applyHold` | C2-S fixes retention list/hold/DML/final event | Source-method witness, not full end-to-end |
| 32 | CF02-FR-032 | Degraded channels | `W:processOutbox / W:processEvents` | Outage does not produce false deliver success | Source-method witness, not full end-to-end |
| 33 | CF02-FR-033 | Automation boundaries | `U:decide / Z:cases` | No autonomous clinical/financial irreversible decision | Source-method witness, not full end-to-end |
| 34 | CF02-FR-034 | Retention and deletion | `O:purgeCase / O:dueRetention` | C2-S fail-closed source; real DB restore pending | Source-method witness, not full end-to-end |
| 35 | CEN-OWN + CV-281 | Platform-wide | `S:assertNoPrivilegeSignals / N:evaluate` | No donation, ranking or provider preference advantage | Source-method witness, not full end-to-end |
| 36 | File 00 + File 02 identity | Platform-wide | `F:current / R:validate` | Mandatory fresh native-owner health and assertion | Source-method witness, not full end-to-end |
| 37 | CV-283 migration truth | Platform-wide | `B:schemaDefects / M:compare` | Live schema/source parity and rollback absent | Source-method witness, not full end-to-end |
| 38 | File 20 single shell | Platform-wide | `Q:register / Q:contracts` | File 20 route receipt required, no duplicate shell | Source-method witness, not full end-to-end |
| 39 | CV-285 emergency governance | Platform-wide | `Y:classifyText / U:decide` | Emergency diversion, independent human override | Source-method witness, not full end-to-end |
| 40 | CEN-REL live-first release gates | Platform-wide | `J:evaluate / O2:validate` | Source green != staged/live/deployed green | Source-method witness, not full end-to-end |

## C2-S: two newly confirmed source-level defect categories

**C2S-01 — Failed SQL list queries were silently converted into empty data.**
- Source: `OperationsRepository::rows()` used `get_results()` then `is_array ? array_filter(...) : []`. In particular, workers, restricted projections, appeals and `purgeCase()` depend on this shared method. Failed/malformed row results must not masquerade as a truly empty dataset.
- Fix: throw `RuntimeException` on a failed or malformed SQL result; preserve a true empty array and ordered valid rows.
- Test: `tests/c2s-retention-sql-failclosed.php` plus existing C2-R private-query tests.

**C2S-02 — Retention deletion and legal hold could be treated as verified despite errors.**
- Source: `purgeCase()` cast failed `COUNT(*)` to zero before checking legal/category holds, then issued several unchecked DELETE and raw SQL operations. `SupportRetentionPurgeCompleted` was previously appended **after** the transaction, allowing deletion and final audit success to separate.
- Fix: only verified `0` or `"0"` permits further purge checks; any hold, failed query, or unknown state denies purge. Added guarded `purgeDelete()`/`purgeQuery()` for every purge child mutation. Final completion event is part of the same transaction as canonical case deletion; failure rolls back rather than leaving a false completed status.
- Tests: `tests/c2s-retention-sql-failclosed.php` exercises SQL null/false/malformed lists, legal-hold unknown/active results, DELETE/SQL write failures and transaction rollback, and verifies the event is inside the transaction. Updated legacy C2-F/C2-I tests to check the *stronger* unknown-or-active legal-hold denial rather than old source literal messages.

## Evidence and restricted interpretation
- PHP 8.1/8.2/8.3/8.4 and five exact-head GitHub workflows: verify the final SHA after this documentary commit.
- At predecessor source commit `69df82a44ee43802784633c79977cf23eeb950e5`: **28/28** new retention failure cases, **40/40** C2-O FR/central method checks, **40/40** C2-R activation cases, **40/40** C2-Q native outcome cases, **35/35** signed metadata cases, and all five workflows successful.
- The 28 new cases are synthetic PHP/fake-DB failures; **not** a real WordPress+MySQL transaction/rollback/recovery acceptance.
- This new review has **two source-level defect categories**, both corrected in the draft PR, **zero defects independently verified fixed on Live**. Other unverified integrations must not be disguised as confirmed source bugs.

## Outstanding barriers: specific owners and gating action
1. **File 00 identity / mandatory companions**: obtain canonical owner version, identity assertion issuer/expiry, fresh healthy signed provider manifest for all 14 mandatory and enabled conditional contracts; no CF-02-generated ready/health claims.
2. **File 18 Marketplace**: main was document-only at the examined baseline, while native source was on separate unmerged PR; require File 18 exact accepted HEAD/artifact, interface contract, deployed parity and staged CF-02 handshake.
3. **File 17/19, CF-01/03 and CF-05 boundaries**: typed request/reference and privacy-safe aggregates only; no direct foreign table writes or clinical, finance, message/identity copying; provider acceptance still pending.
4. **Provider webhook authentication**: authentic timestamp/body HMAC does not alone prove canonical signer ↔ claimed source_owner/method/route/purpose binding; native provider and Founder must approve any versioned signature protocol changes before code/deploy.
5. **Real WordPress/Hostinger live reality**: exact installed plugin/version/package checksum, database tables/indexes/rows, schema/options/migration state, runtime errors/logs, active dependencies and parity require authorized read access. Previous WPVibe path returned Cloudflare HTTP 530, which does not identify DNS/origin cause.
6. **Staging acceptance**: real database foreign-key/collation/storage-engine and atomicity, concurrent legal holds/purges, rollback/restore, provider outages, concurrency/load, independent security and manual accessibility/Urdu RTL tests.
7. **Future24**: guarded code foundations only; not 24 independently approved operational UI/mobile/provider workflows. No public activation or new persistence without Change Control.
8. **Release**: verified zero known unresolved critical/high production defects, exact package checksum/source parity, Founder signed approval, deploy, monitored live re-test and rollback window.

## Mandatory incident fields
- Repository `main` at review start: `bd52b96af28ce775a18860142aec84a62fb9b803` (source truth, not live).
- Corrective GitHub branch: `audit/cf02-crossplan-contract-parity-20261021`, draft PR #10. Query its exact final HEAD after this doc update.
- Deployed Version: **unverified**.
- Live DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not performed**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
