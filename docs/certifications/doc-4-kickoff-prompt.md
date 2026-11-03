# Doc 4 kickoff prompt

Start Doc 4 — Certification Lifecycle & Completion for TA Tracker.

First read AGENTS.md, docs/certifications/spine.md (including the latest handoffs),
doc-3-capture-and-progress.md, doc-2-people-and-enrollment.md, the 1A follow-up in
doc-1-catalog-foundation.md, and docs/form-conventions.md. Inspect the current enrollment,
progress/evidence, certification duties, certificate editor, models, enums, routes, policies,
services and migrations. Distinguish implemented schema from planned tables and enum values
from working lifecycle transitions. If a Doc 4 specification already exists, reconcile it
with the latest handoffs before proposing changes.

Context:
- Docs 1/1A, 2 and 3 are complete. Verify the actual branch, recent commits and working-tree
  status before proceeding; preserve all existing work. Do not assume old handoff notes
  saying “uncommitted” still describe the checkout.
- Certificates must ship independently of design/enterprise-restyle. Use this branch's
  existing Bootstrap/Blade styling and shared components. Preserve the approved activity
  capture and progress/verification experience.
- Do not stage, commit, push, switch branches or create a worktree during this first pass.
  Development data must be preserved; migrations are forward-only. Never reset, truncate or
  destructively reseed. Existing evidence, option IDs, enrollments and assignments matter.
- The local NWIC catalog is configured through the explicit local/testing-only
  NwicCertificationSeeder: Local Coach, Supervisor and Trainer, with all 74 initial/renewal
  requirements from the Spring 2026 guide. Read the mapping in the Doc 3 handoff and inspect
  actual records instead of substituting a simplified demo certificate. The seeder is not
  part of the default production seed. Do not fabricate awards or migrate demo data into
  production. You may add clearly identified local test/demo examples without harming
  existing records.

This first pass is UX and planning. Use impeccable to shape the experience. Do not implement
application changes until I approve the draft.

Deliver:
1. Interactive HTML UX drafts for completing the certification lifecycle from the existing
   enrollment progress page: coach endorsement, manager review and award, issued credential
   details, manual revocation with a required reason, expiration, and beginning/completing
   renewal cycles. Keep the candidate, certificate, cycle, supervising coach and next action
   clear. Retain breadcrumbs to the originating visible agreement; agreement context is
   navigation/provenance, never a credit or eligibility rule. Do not expand into a full
   candidate directory or redesign the activity form.
2. Representative states: incomplete requirements; requirements met awaiting endorsement;
   endorsed awaiting award; blocked prerequisites; evidence or catalog changes after
   endorsement; duty/access loss or coach reassignment; already awarded; expired; revoked;
   renewal in progress while an earlier award remains valid; successful renewal; stale pages
   and duplicate/concurrent actions. Explain unavailable actions and recovery without
   exposing detailed evidence to ordinary viewers. Show validation, confirmation/cancellation,
   historical award viewing and an understandable action history.
3. A precise lifecycle/authorization proposal: allowed transitions and actors, how live
   progress relates to stored lifecycle state, endorsement validity/withdrawal, award-time
   revalidation, prerequisite award eligibility, expiry semantics, cycle boundaries and
   repeat awards. Inspect existing system-admin Gate bypass behavior and explicitly show
   how duty requirements remain enforced. Identify policy choices instead of inventing them.
4. Self-contained HTML diagrams: a lifecycle state/transition flow, plus an ER diagram with
   cardinalities and implemented/planned distinctions for enrollments, endorsements, awards,
   snapshots and action logs. Show how the previous award date reaches the existing live
   progress service. Preview with html_preview and publish with html_render.
5. A concise Doc 4 implementation plan: scope, forward-only schema additions, service/policy
   boundaries, transactions and concurrency protection, snapshot/history contents, renewal
   integration, any scheduled expiry work that is actually necessary, meaningful regression
   tests, acceptance cases, documentation updates and open decisions. Recommend the smallest
   complete implementation and separate confirmed rules from proposals requiring approval.

Respect the spine and completed work:
- Candidates are external people without logins. Enrollments are global candidate/certificate
  pairs; sponsoring agreements and activity tag agreement IDs are provenance only.
- Credit uses program intersection. Preserve activity tags with zero/many tool submissions,
  role and completion-unit filters, required tool dimensions, explicit coach-judged outcomes,
  and optional recorded scores. Scores and thresholds never determine pass/fail automatically.
- Progress remains computed live. Preserve rolling windows and cycle boundaries. Cycles after
  the first must receive the actual previous award date; the current service fails closed
  when it is missing. Evidence must be after that boundary and within its effective window.
  Do not materialize progress or treat requirement completion as an award.
- Totals, phase coverage and review quotas independently use the same eligible/passing pool;
  one submission can satisfy all three. Preserve the confirmed distinction between Supervisor
  Checklist Full reviews and COMET field observations.
- Coach endorsement is a hard gate: requirements met → coach endorses → certification manager
  awards. Duty and visibility are separate: a duty grants no implicit record access, and
  admin/manager access alone does not grant coach judgment. Preserve existing scoped duties;
  provide manager-duty administration only where the lifecycle actually needs it.
- Ordinary authorized viewers see general status or requirement counts. Detailed progress and
  evidence remain restricted to authorized admins, coaches and managers within visibility
  and scope. New award/history surfaces must follow an explicit visibility proposal.
- Validate configured all/any certificate prerequisites using real award records; NWIC Trainer
  requires Local Coach OR Supervisor. Identify decisions about expired/revoked prerequisite
  credentials, renewal and the timing of checks. Do not infer policy from labels alone.
- Snapshot requirements onto each award when issued so later catalog edits cannot invalidate
  it. Propose enough snapshot context to explain the award without making historical validity
  depend on mutable live evidence. Record actor, time, cycle and reason/context for lifecycle
  actions. Preserve prior awards and audit history across renewal.
- Never automatically revoke an award when evidence changes or rolls out of a window.
  Revocation is manual, requires a written reason and is logged. Keep credential expiry
  distinct from evidence aging and from revocation. Certificates may have no expiration.
- Preserve linked versus independent renewal configuration, cycle-scoped attestations and
  genuinely different renewal requirements. Show how current award validity and next-cycle
  progress coexist without discarding history or duplicating the global enrollment.
- PDFs, notification delivery, broad central indexes and a full candidate directory remain
  Doc 5 unless I explicitly expand scope. Doc 4 should complete the operational certification
  process without depending on them. Automatic COMET inter-rater sequencing remains deferred.

Ask one concise round of material questions if needed, after checking the repository for
answers. Finish with the recommended UX, tradeoffs and explicit approval points. Once the
draft is approved, implementation must include meaningful authorization/lifecycle tests,
the repository's required checks, browser verification, impeccable critique and updated
Doc 4/spine handoffs. Leave all code changes unstaged and uncommitted for my review.
