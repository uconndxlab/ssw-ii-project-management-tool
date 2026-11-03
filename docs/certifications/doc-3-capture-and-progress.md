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

## User-review follow-up — 2026-10-26

The original Doc 3 implementation is committed as `b5c8356`; these follow-up changes remain
unstaged on `feature/certificates-doc3`.

- Progress breadcrumbs now show Agreements → the originating visible agreement → candidate.
  Agreement and activity links carry `agreement_id`; confirmation saves preserve it. The server
  validates navigation context against the candidate's visible roster agreements, falling back
  to visible sponsorship or another visible roster agreement. This never scopes progress credit.
- An agreement assignment with evidence displays In progress even when its stored initial
  lifecycle status is still `not_started`. Unstarted assignments remain Assigned. Displaying
  progress does not mutate status or execute Doc 4 lifecycle transitions.
- Opening a candidate editor positions that candidate below the phone and compact form headers
  after the previous editor collapses. The existing keyboard focus restoration remains in place.

### Full NWIC development catalog

Source: user-supplied **NWIC Certification Guide Spring 2026**, 14 pages. Requirements were read
from pp. 5–9 and 12–14, with course descriptions/prerequisites from pp. 10–11. Key proficiency
and renewal pages were also inspected visually. `NwicCertificationSeeder` is an explicit local/
testing-only setup; it is not part of `DatabaseSeeder` and makes no production or schema changes.

Run with `vendor/bin/sail artisan db:seed --class=NwicCertificationSeeder --no-interaction`.
The NWIC program must already exist. The setup has been applied to development:

- **NWIC Local Coach** (certificate 11): 19 initial requirements and 10 renewal requirements.
  Initial: registration, online prerequisites, six core courses, Supervisor Checklist 6 / phases
  2–4 / Full quota 4, STEPS confirmation, CREST 12, COMET inter-rater confirmation and COMET
  6 / phases 1–4 / field-observation quota 4, SAS 3, training contributions, all scheduled NWIC
  coaching, platform use, WVCC 3 in a year, and developmental period/regular-submission review.
  Renewal: WVCC 3 plus one annual local contribution, monthly calls, platform/data use, boosters,
  Supervisor Checklist 3 / phases 2–4 / Full quota 2, STEPS, CREST 6, COMET 3 / phases 2–4 /
  field-observation quota 2, and SAS 3.
- **NWIC Supervisor** (certificate 12): 19 initial requirements and 11 renewal requirements.
  Initial: registration, online prerequisites, five core courses, onboarding plan, Supervisor
  Checklist 6 / phases 2–4 / Full quota 4, staff skill-development confirmation (six/twelve-month
  guidance), STEPS, CREST 12, COMET understanding/skill-development confirmation, SAS 3, boosters,
  scheduled coaching, platform use, WVCC 3 in a year, and developmental period/regular-submission
  review. Renewal keeps Supervisor Checklist at 6 / Full quota 4, reduces CREST to 6, retains
  staff-development/STEPS/COMET confirmations and SAS 3, plus annual WVCC participation/contribution,
  monthly calls, platform/data use and boosters. No invented supervisor COMET submission count.
- **NWIC Trainer** (certificate 13): 11 initial requirements and 4 renewal requirements.
  Initial: eight activity requirements covering Participant / Observer / Co-trainer / Observed
  trainer for both 101 and 102, plus delivery/mastery, IOTTA data review and booster confirmations.
  Coach OR Supervisor is configured as the prerequisite. Renewal confirms maintained certification,
  IOTTA for every training (including the guide's one-month/two-day/three-day submission timing),
  recording/upload of introductory and engagement trainings, and boosters.

All three use annual validity, independent renewal requirements and 12-month renewal windows.
Initial work has no blanket rolling expiry; the initial WVCC requirement has its explicit 12-month
window. There are 74 requirements total: 49 initial and 25 renewal. Quantitative minimums are
separate from coach judgments. Scores and matching percentages remain display-only guidance.
The Local Coach 18–24-month and Supervisor 12–18-month development periods are manually reviewed,
not fabricated automatic duration rules. Site-specific adaptations remain out of scope.

The five tools are Supervisor Checklist, STEPS, CREST, COMET and SAS. Checklist Full means document
review plus observation; COMET's quota means face-to-face field observations. Required dimensions,
percentage score fields and the four SAS point fields are configured. The course catalog includes
101/102/201/202/203/401/402/501, the three online prerequisite modules and WVCC. Courses 202/203 are
available catalog entries, not silently added to the guide's initial core-course requirement lists.

The setup adds missing records and updates its labeled requirements in place. It preserves existing
people, assignments, evidence, option IDs and unrelated catalog records. It handles renamed demo tools
and obsolete slugs without repurposing one canonical instrument as another: existing COMET still has
its historical `crest` slug, so the separate CREST uses `crest-nwic`. Repeated setup keeps requirement
IDs stable. Existing demo enrollments and their intentionally partial progress remain unchanged;
no candidates were automatically assigned to the new certificates.

Formal prerequisite award validation, endorsement, awards and renewal execution remain Doc 4.
COMET's prior inter-rater review is captured by a coach confirmation; automated sequencing is still
deferred. IOTTA deadlines and uncounted ongoing participation/proficiency expectations are manually
confirmed, with source-page notes on the requirements.

Verification: `vendor/bin/sail composer check` passed (298 tests / 1,271 assertions; audit, Pint and
PHPStan), and the production build passed. Tests cover the guide configuration, repeatability/data
preservation, breadcrumb visibility/context and stale status display. Playwright verified candidate
switching at 1440px and 390px, breadcrumb links and assignment badges, without console errors or page
overflow. Screenshots and review/check artifacts remain gitignored in `.artifacts/doc3/`.
