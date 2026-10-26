# Doc 3 — Certification Capture & Progress

Approved for implementation on 2026-10-26 from the interactive drafts in
`.artifacts/doc3/{capture-drafts,progress-draft,diagrams}.html`.

## Approved experience

- Keep activity logging fast: hide Certification without roster people or existing evidence;
  otherwise start with Add candidates. One candidate row opens at a time. Tags carry a role,
  completion units (default 1), notes, and zero or many separate tool submissions.
- Candidate choices come from selected agreement rosters. Saved people that become unavailable
  remain visible. Roles derive from the person's certificate requirements matching the activity
  classification. Set role for all applies only where the role is eligible.
- Coaches select a tool, all its dimensions, optional recorded scores, and an explicit outcome.
  No default verdict, score math, or automatic pass/fail. Saved submissions keep their tool;
  removal is explicit and undoable until the activity is saved.
- One Needs certification review flag per activity. A coach needs existing activity edit access
  to resolve it. Existing activity authorization is preserved: logger, Delivered by participant,
  or an admin whose programs/projects cover the activity. Agreement viewing alone is insufficient.
- Ordinary viewers receive only overall progress / requirement count. Detailed requirements,
  evidence, scores and confirmations require existing enrollment visibility plus admin authority
  or coach/manager duty intersecting the certificate's scope. Duties never grant visibility.
- A coach in scope records submissions and confirmations. Admin or manager status alone permits
  detailed viewing, not coach judgments. Confirmations live on the enrollment progress page,
  reached through agreement assignments and tagged activities. No candidate directory.

## Implementation contract

Four forward-only migrations add activity tags, submission/dimension/score tables, cycle-specific
attestations, and the activity review timestamp/actor. The tag table is pluralized
`activity_certification_candidates`; provenance agreement is nullable and never scopes credit.
No upload column or award/lifecycle schema is added. Preserve development data and existing IDs.

Validate nested IDs against their owning activity, tag, tool, and dimension. Save certification
with the existing activity transaction after program synchronization. Explicit deletion markers
prevent omissions from silently deleting evidence. Non-coaches cannot change submission payloads
or remove a tag holding submissions. Preserve saved out-of-scope or retired selections. Protect
used tool dimensions/options/score fields from deletion. Duplicates copy no certification data.

Compute progress live per enrollment. Filter candidate, completed/non-cancelled activity,
classification, role, certificate/activity program intersection, rolling date window and cycle
boundary. Tool requirements use the same eligible/passing pool independently for total, coverage,
and quotas. Scores never enter evaluation. Evaluate all/any/n-of groups, including linked renewal.
Doc 4 must supply the previous award date for cycles after the first; until supplied those cycles
fail closed. Requirements met is never an award or endorsement.

## Verification and acceptance

Cover zero/multiple submissions, forged ownership and option IDs, dimensions and outcomes,
scope/role eligibility, retired selections, preservation after roster changes, review flag and
coach-only writes, summary-only responses, and used-definition protection. Service coverage must
include NWIC six-submission totals with four Full reviews/field observations independently,
coverage/quota failures, non-passing outcomes, rolling boundaries, cycle boundaries, cancellation,
unit sums, All scope, groups, attestations and linked renewal. Exercise activity and progress UI
on desktop and mobile with Playwright, inspect console errors, then run project checks.

Endorsement, awards, revocation, renewal execution, PDFs, notifications and inter-rater sequencing
remain deferred. Local test tool configuration may be corrected for demos without replacing IDs.


## Completed handoff

Implemented on `feature/certificates-doc3`, uncommitted for code review. The four new migrations
are applied locally; existing development data is intact. Coach recording is scoped by the shared
activity/tool/certificate program intersection. Evidence prevents removal through the old unstarted
assignment shortcut. Unchanged saves retain original recorder, child evidence IDs and timestamps.

`vendor/bin/sail composer check` passes: 292 tests / 1,189 assertions; dependency audits, Pint and
PHPStan pass. `vendor/bin/sail npm run build` passes. Two independent impeccable assessments found
no blockers; focus, dynamic field help/error associations, and concrete eligibility guidance were
fixed. Playwright confirmed the fixes, training selection, multi-submission save, removal/undo,
live confirmation, and no horizontal overflow at 390px, with no console errors.

Review links: activity `/activities/42/edit`; enrollment `/certificate-enrollments/3`. Screenshots,
check logs and critique evidence are in gitignored `.artifacts/doc3/`. The demo preserves original
COMET option IDs and distinguishes field observations from checklist Full reviews.

No staging or commits. No dependencies added. Doc 4 remains responsible for lifecycle execution.
