# Doc 2 — People and Enrollment

Prerequisite: read [spine.md](spine.md) and
[implementation-plan-1a-and-2.md](implementation-plan-1a-and-2.md).

Status: Doc 2 complete and reviewed in the working tree on `feature/certificates-1a-doc2`.
Nothing from this review is committed or released. **1A is also complete in the working tree**; its
authoring behavior and verification are recorded separately in [doc-1-catalog-foundation.md](doc-1-catalog-foundation.md).

## Delivered scope

- External certification candidates, separate from staff users and without logins.
- Agreement roster links to those people; optional certificate enrollments and supervising coaches.
- Main-column certificate assignment summaries with coach, assignment date, and direct edit links.
- Coach/manager duty controls on the existing authorized admin Edit User form.
- Legacy backfill, preservation rules, scope checks, validation, and regression coverage.

Activity tagging, tool submissions, progress computation, endorsement, awards, renewal execution,
candidate directories, merging, global person deletion, and notifications remain outside Doc 2.
No progress percentages or automatic completion credit have been implemented.

## Stored records

- `certification_candidates`: name; nullable unique normalized email; optional phone, start date,
  and notes; active flag; timestamps. Email is trimmed and lowercased on write.
- `agreement_certification_candidates`: existing agreement-local rows converted to roster links.
  Each has `agreement_id`, required `certification_candidate_id`, deterministic `sort_order`,
  roster-local notes, and its preserved legacy `program_id`. The UI does not edit that program.
  A unique constraint prevents two links for the same agreement/person.
- `certificate_enrollments`: unique candidate/certificate pair; optional sponsoring agreement and
  supervising coach; status, phase, cycle, enrolled date, and notes. New rows start
  `NotStarted` / `initial` / cycle 1. Lifecycle fields are not accepted from the browser.
- Sponsor deletion sets the reference to null. Agreement deletion removes its roster links but
  preserves people and enrollments. Candidate/certificate foreign keys cascade at the database
  level; no candidate-delete UI exists, and the certificate controller retires assigned certificates
  instead of deleting them. Future deletion paths must preserve these protections.

The original four migrations are dated `2026_10_23_120000`, `120100`, `120200`, and `120300`.
The initial conversion preserved one person per original name row so no relationship was discarded.
User clarification supersedes that initial identity assumption: repeated legacy names represent the
same external person and must be reused across agreements.

`2026_10_25_131838_reconcile_legacy_certification_candidates` is the forward correction. It groups
normalized legacy names (case and whitespace), keeps one canonical person, repoints roster links and
enrollments, and removes redundant people. A known-email identity is preferred when exactly one exists;
otherwise the oldest person ID is retained. Compatible duplicate roster placements/initial unstarted
assignments combine notes and preserve their available metadata. Conflicting identity values, roster
programs, coach/sponsor values, or duplicate lifecycle history abort the transaction for review.
Blank names do not establish identity. Separately identified same-name people with distinct emails
remain separate; there is no global unique-name constraint.

The correction is applied locally: **26 people → 10 shared candidates; all 26 roster links and the one
enrollment retained their metadata**. Before/after data snapshots are gitignored under `.artifacts/`.
Reconciliation is forward-only; `down()` refuses to invent the removed identities. The original applied
migrations remain unchanged. The demo seeder now reuses its trusted named people, and agreement copies
continue to reuse candidate IDs.

## Agreement save and display flow

`AgreementRequest` authorizes the surrounding agreement and validates nested payload shapes.
Its validation callback delegates certification rules to
`App\Services\CertificationAssignmentSyncService`. Malformed candidate/assignment rows produce
field errors; null roster input is handled as an empty submission.

The agreement controller saves agreement fields, scope, roster, and enrollments in one transaction.
The certification sync locks the agreement and referenced existing people in deterministic order,
then revalidates sensitive rules against the saved agreement scope. Unique database constraints
protect roster/enrollment identity. A concurrent email conflict becomes a field validation error;
the surrounding agreement transaction rolls back.

The same filtered enrollment display feeds both the edit and show pages. It intersects certificate
visibility with the current agreement's effective programs. Shared people can appear on several
agreements with different visible subsets of their enrollments. Sponsorship is provenance; it is
neither permission by itself nor the future evidence-counting boundary.

## Person selection and edits

- New people require a name and valid unique email.
- Untouched migrated people may retain null email. Selecting one, assigning a certificate, or saving
  unrelated agreement changes does not force an email or overwrite their identity.
- Actual name/email changes require a valid unique email and either system administration or update
  authority over every agreement linked to that person. Duplicate normalized emails within the same
  submission are rejected before writes.
- A saved roster row cannot be switched to another person. Remove its link and add a new row.
  A new row's person selector resets stale identity, notes, and unsaved assignment fields.
- Existing-person identity is read-only until an authorized editor chooses **Edit shared person
  details**. This is available only with shared-edit authority and clearly warns that changes affect
  every linked agreement. Certificate/coach edits do not unlock identity fields.
- Candidate search matches name or email through the existing token picker in single-selection mode.
  Choices show only the person and their email when known; agreements and internal IDs are omitted. Already-linked people are excluded from new choices. Known email
  entered for a new person offers **Use existing candidate**, which fills the stored name/email only
  after explicit selection. If that person is already on this roster, **Edit existing candidate** opens
  their existing row instead. Unknown emails do not fabricate or infer names.
- Switching people clears draft notes/assignments, with confirmation when selected certificate rows
  would be discarded. Removing a search selection restores the blank new-person fields.
- Inactive people are excluded from new selection and new enrollments; existing roster links and
  enrollments remain visible and usable for unrelated saves.
- Legacy people remain selectable without emails. Their reconciled identity is shared across every
  linked agreement, so existing assignments are reused and email is collected only on actual identity
  edits. Future verified profiles use email uniqueness; this legacy cleanup is not an automatic
  name-based merge of every new person.

## Assignments and supervising coaches

`eligibleCertificates(actor, mode, programIds)` uses active, non-retired, viewer-visible certificates
applicable to the agreement's programs. Assignment does not require catalog-edit privileges.
`All` still respects actor visibility; `None` permits no new assignments. Existing inactive/retired
enrollments are preserved and do not make an ordinary save fail.

A candidate/certificate pair has one global enrollment. Reusing it from another roster does not reset
its dates, sponsorship, or coach. Omitting an enrollment from a form does not delete it. New
enrollments default sponsorship to the agreement where they are created.

`eligibleCoaches(programIds, actor, agreement)` requires active staff, an active matching coach duty,
actor-visible staff records, and the coach's own agreement visibility. Server validation intersects
the duty with the selected certificate's relevant programs, not any unrelated program on the agreement.
Duty alone grants no access. A coach is optional.

Explicit coach correction requires the saved enrollment ID and submitted coach field, authority over
the shared enrollment, and an initial, NotStarted, cycle-1 enrollment. The new coach must qualify;
clearing the coach is allowed. Unchanged coaches that later become unavailable are preserved.

Global enrollment removal has the same unstarted-cycle boundary and requires confirmation.
A system administrator may manage it; other actors need update authority over every linked agreement,
the sponsor if present, and the program-specific certificate. An All-program certificate uses the
affected agreement context. A missing sponsor grants no extra authority. Noninitial, started, or
later-cycle records cannot be corrected or removed through this assignment flow.

Removing a roster row detaches only that agreement/person link. It does not delete the person or
their enrollments. Agreement duplication reuses person IDs and roster metadata without duplicating
enrollments, changing sponsorship, or restarting cycles.

## Form behavior

The existing Bootstrap form and repeater components are retained. Compact roster summaries show
identity, certificate count, and certificate names. Adding a row first offers candidate search or
explicit new-person creation. Identity fields appear only for new people or authorized shared-person
edits; agreement notes are optional disclosures. One candidate editor is focused at a time; its
certificate/coach rows are flat and immediately editable, without nested Edit/Done actions. Text
Edit/Done/Remove actions, stable field IDs, associated errors, and current counts remain. Candidate
sorting is not exposed as a drag-only action. The candidate list grows with its content rather than
trapping the editor in an internal scrolling area.

The shared token picker adds an opt-in single-selection mode; existing program/duty pickers remain
multi-select. A repeater row-added event initializes new pickers before they receive focus. Empty
candidate result lists close so they cannot cover the Add a new person action.

Certificate and coach options update with the agreement's selected programs and certificate context
without a new endpoint. Selected values that become invalid remain visible with validation feedback.
Existing unavailable coaches remain selectable as preserved history. Client validation opens enclosing
editors before focusing invalid inputs. Remove confirmations distinguish agreement detachment from
global assignment removal; new-row removal restores focus and announces the pending change.

Choosing a shared person immediately loads their existing visible, applicable enrollments, including
the saved enrollment ID, coach, and edit/removal authority. Changing agreement programs updates that
subset. Reusing these rows does not create enrollments or reset metadata. Assigned certificates are
excluded from additional choices; the add action explains when the current context has no more choices.

The show page has a dedicated **Certificate assignments** section in the main column. It shows people,
certificates, coaches, and assignment dates with direct links to the relevant agreement-form editor.
An initial unstarted enrollment is labeled **Assigned**. This is assignment metadata, not calculated
completion. Requirement counts, activity/tool credit, and progress remain Doc 3; endorsement, awards,
and renewal execution remain Doc 4. No progress bar, percentage, completed count, or award action is implied.

## Duty administration and authorized self-grants

The admin Edit User form provides system-wide duty switches for system administrators and program
token pickers limited to the actor's grantable scope. Selected program tokens are visible and removable.
Both duty payloads are validated before user fields are saved. Omitted duty fields leave existing duties
alone. Each rendered duty editor submits a presence marker so an empty selection revokes its last
authorized program. Explicitly submitted changes preserve rows outside the actor's grant/revoke authority.
Revoked duty rows are retained with actor/timestamps.

Authorized admins can grant/revoke their own duties in **Profile → Edit Profile → Certification
duties**. The dedicated `PUT /profile/certification-duties` route is named
`profile.certification-duties.update` and uses `UpdateCertificationDutiesRequest`. It requires an
active authenticated admin, permits system duties only for system admins, and restricts program
scopes to programs the actor administers. Out-of-scope submissions are validation errors. The action
always targets the signed-in user and passes only validated duty data to the existing sync service
inside a transaction. Posted name, email, password, or access-profile fields cannot alter the account.

`UserPolicy::update()` still forbids general self-edit through the admin-user route. Profile has
separate password and duty forms: the main action is explicitly **Save password**, while duties use
**Save certification duties** without requiring password fields. Existing retained-revocation and
outside-scope preservation rules apply to self-grants too. Duties outside the actor's current admin
scope are excluded from editable selections and retained, so a later scope change does not make
the form unsavable or silently revoke historical duties.

## Review corrections and verification

The generated implementation exposed out-of-program enrollments on edit, allowed unsafe global
removal when sponsorship was absent or other linked agreements were inaccessible, mishandled retired
selections and malformed payloads, accepted invalid coach scope/visibility, and revoked omitted duties.
These defects were reproduced and corrected. Same-email edits, inactive people, forged roster IDs,
person switching, and explicit coach correction now have regression coverage.

Tests:
- `tests/Feature/Certification/CertificationCandidateAssignmentTest.php`: original assignment,
  preservation, duplication, deletion, reachability, idempotency, and page-render coverage.
- `tests/Feature/Certification/CertificationDutyGrantTest.php`: duty grant/revoke boundaries and
  retained revocations, including the service-only self-grant case.
- `tests/Feature/CertificationCompletionTest.php`: ten cases covering HTTP self-grant/revoke,
  privilege preservation, scoped-admin restrictions, member/guest rejection, malformed payloads,
  candidate lookup privacy, recovery to a working editor after invalid fields, immediate assignment
  data for shared-person reuse, actionable show-page facts, and saved-assignment controls/coach
  selection retained after validation errors on a new roster link.
- `tests/Feature/CertificationCandidateReconciliationTest.php`: eight cases for normalized legacy
  identity reuse, roster/enrollment preservation and compatibility, known-email preference, distinct
  modern identities, atomic conflict handling, blank names, and idempotency.
- `tests/Feature/CertificationAssignmentRegressionTest.php`: 25 regression cases, including an
  isolated PostgreSQL-schema migration replay. That test preserves duplicate legacy names, metadata,
  ordering, and null emails without changing the development schema.

Review checks: Sail Pint passed; targeted certification/profile tests passed (63 cases); required
`vendor/bin/sail composer check` passed, including audit, formatting, PHPStan and the complete
252-test suite (978 assertions); frontend production build passed. Browser verification used Playwright
MCP without saving application forms. Two independent Impeccable assessments found no remaining
blockers after the scope/help-copy refinements. The targeted detector returned zero findings.
Native preview required authentication, so the project’s Playwright workflow supplied runtime evidence. Screenshots and
the earlier interactive review/proposals are in gitignored `.artifacts/`.

The browser pass checked keyboard candidate search, person switching, explicit identity edits,
known-email reuse (browser-response fixtures, without stored data changes), existing-roster focus,
assignment counts, coach editing, fixed certificate identity, and mobile layout. Long certificate
summaries truncate within the compact row and keep their full text available on hover and in the editor.
The Profile duty form and ordinary multi-select pickers were checked separately. Existing unrelated
organization/KFS controls cause slight page-wide overflow on mobile; certification controls fit.
This was a functional review of the changed flow, not exhaustive assistive-technology certification.

## Handoff

- **1A is complete in the working tree.** Its four-section catalog editor, contextual grouping/roles,
  renewal configuration and full rule review are documented in Doc 1 and the spine. No candidate
  identity, assignment or progress behavior was added as part of that authoring follow-up.
- The approved assignment direction is implemented: name/email search, reconciled legacy reuse,
  explicit email reuse, compact summaries, a focused search-or-create editor, immediate existing
  assignments, and explicit shared-person edits. The earlier HTML remains
  an illustrative design artifact; application behavior is authoritative.
- Doc 3 must add candidate activity tagging, submission dimensions, evidence, and live evaluation.
  Do not infer credit from legacy names. Preserve the guide's phase/review quotas and coach-judgment
  boundary; automated inter-rater sequencing remains explicitly deferred.
- Doc 4 must add formal endorsement, award, renewal-cycle execution, and the missing lifecycle fields.
  It must define supervision changes beyond this initial unstarted-assignment correction flow.

### Combined 1A / Doc 2 verification

After 1A completion, the full `vendor/bin/sail composer check` passed again: 266 tests / 1,062 assertions,
including the existing candidate, assignment, reconciliation and duty suites. Audit, Pint, PHPStan and
`vendor/bin/sail npm run build` passed. Doc 2's earlier 252-test result above is historical; the combined
release handoff is now this result plus the 1A handoff in Doc 1 and the spine. Both workstreams remain
uncommitted and unreleased. Automatic activity/submission credit and candidate progress are Doc 3;
endorsement, awards and renewal execution remain Doc 4.
