# CF-02 Review and Correction Register

## Round 1 — Requirements, ownership and configuration review

Status: corrected and retested on the development branch.

Findings and corrections:

1. Mandatory companion contracts covered only Files 00, 20, 24 and 25. Added versioned owner/capability manifests for Files 09, 17, 18 and 21 as required for native decision references.
2. Operational activation gates accepted bare booleans. Replaced them with structured, timestamped evidence records and measured volume/staffing checks.
3. Support categories and queue definitions accepted malformed or duplicate list values. Added identifier, type, uniqueness and skill-catalog validation.
4. Queue validation did not prove that category-required skills were available. Added full category-to-queue skill coverage checks.
5. Queue ownership roles were unvalidated strings. Bound queue owner and escalation roles to the canonical staffing-role enum.
6. Change-control records lacked strict list, requirement-ID and approved timestamp validation. Added validation and negative tests.
7. Founder activation identity and activation change-control ID were weakly constrained. Bound approval to the canonical `founder` identity and `CF02-ACT-###` format.

## Round 2 — Fresh adversarial least-privilege review

Status: corrected and full CI pending/required on the exact final head.

Findings and corrections:

1. Non-string category/queue values reached duplicate checking before type validation. Reordered validation to reject malformed values first.
2. Account and verification categories shared one identity queue despite different native authorities. Split them into purpose-specific queues.
3. Privacy-right and safety/abuse cases shared one sensitive queue, allowing avoidable cross-purpose visibility. Split them into separate liaison queues.
4. Measured activation evidence could claim `triggered: true` even when the observed value was below threshold. Added numerical consistency checks.
5. Staffing evidence allowed blank queue-owner assignments. Added key/value validation.
6. Adversarial tests were expanded for Founder spoofing, invalid activation IDs, missing owner capability, boolean-only evidence, false volume triggers, blank staffing assignments, malformed configuration and purpose-separated queue mapping.

## Truthful completion state

These reviews cover the Phase C2-A foundation only. They do not prove package, staging, live or operational completion. Any CI failure, new defect report, companion-contract change, security advisory or staging evidence reopens this register and requires another review/fix cycle.
