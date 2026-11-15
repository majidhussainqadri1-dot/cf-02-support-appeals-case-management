# C2-N — Corrective Review and Evidence Register

**Baseline:** `bd52b96af28ce775a18860142aec84a62fb9b803` (main).
**Candidate:** `agent/cf02-v12-3-alignment-20261115`, plugin `1.0.0-rc.8`.
**Live status:** unverified; does not inherit GitHub CI.

## Review round 1 — Security, trust and data separation

| Audit | Finding | Source correction | Regression |
|---|---|---|---|
| Guest route visibility | Safe guest pre-intake backend existed but public portal only displayed authentication wall | Bounded category-specific public form and File 00-controlled continuation | `c2n-v12-3-alignment.php` |
| Sensitive intake | Guest must not request passwords/OTP/payment/clinical records or expose signed payloads in URLs | Source-derived allowlisted categories, secret warnings, encrypted short-lived same-tab token | `c2n-review1.php` |
| Emergency routing | HTTP 422 diversion was initially displayed as a generic failure | Explicit safe specialist/emergency guidance, with no false case-created status | `c2n-review2.php` |
| Administrative CSP | Previous operations UI returned in-page `<script>` and `<style>` | External JS/CSS, DOM `textContent`, per-page capability-bound enqueue | `c2n-review2.php` |
| Unauthorized admin error | `wp_die` used numeric second argument (title, not response code) | Explicit `['response' => 404]` | Deployment/browser test pending |

## Review round 2 — Visual, language, trace and packaging

| Audit | Finding | Source correction | Regression |
|---|---|---|---|
| Legacy green | Component fallback was `#198754` despite File 25 `#087A4E` | Canonical brand, hover, light surface and control border palette | `c2n-v12-3-alignment.php` |
| Dark surfaces | Form input remained fixed white; limited dark support | Theme CSS properties, dark media/explicit mode, forced-colors and reduced motion | Source regression; browser contrast pending |
| Contract docs | Current production/release docs referenced older `1.1.0` while source/manifest used `1.3.0` | Current release docs reconciled; historical C2-L/M versions retained as history | `tests/release.php` |
| Release identity | Prior candidate rc.7 was being changed in place | rc.8 across manifest, entrypoint, WordPress readme, release/smoke workflows | CI and release package |
| Legacy regression assertions | C2-J rc.7 and C2-K old green had become stale | Updated assertions only where governing release/brand law superseded them | C2-J/C2-K suites |
| Urdu locale | New UI strings absent from the compiled catalog | Updated PO, POT and compiled MO; catalog count/headers checked | `c2n-review2.php` |

## Quality governance

- Existing C2-A…C2-M regressions and PHP 8.1–8.4 jobs are not bypassed.
- C2-N uses one implementation suite and two separately authored source-review suites. These are **automated reviews**, not a substitute for an outside security audit or human independent acceptance.
- Node syntax tests cover `cf02-frontend.js`, `cf02-guest.js`, and `cf02-admin.js`.
- GitHub exact-head CI and deterministic package outcomes must be read from the final branch SHA, not copied from historical runs.
- Real Hostinger/browser/security/performance/restore checks have **not** yet passed in this code repository.
- A correction becomes **Live Resolved** only after exact deployed build, DB/schema and migration evidence, deploy, live re-test and parity confirmation.

## Acceptance issue inventory

**Source corrective findings:** 7 change areas recorded in `CHANGE-CONTROL-C2N.md`.  
**Live-proven defects fixed:** 0 claimed.  
**External gates:** minimum 9, as declared by `release/manifest.json`; no fabricated pass statuses.


## Executed JavaScript behavior tests (source-level)

The additional permanent Node test files are `tests/c2n-guest-ui.test.cjs` and `tests/c2n-admin-ui.test.cjs`, both executed by exact-head CI **and** the deterministic release workflow. They simulate (not replace real-browser testing): safe guest receipt, emergency diversion, false-receipt rejection, authenticated token clearance, expiry, provider retry, denied admin surface, text-node-only queue display, repair cancellation and explicitly confirmed repair. The **10 simulation scenarios** are distinct from actual WordPress integration and external security acceptance.
