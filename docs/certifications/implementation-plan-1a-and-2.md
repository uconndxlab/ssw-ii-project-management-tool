# Certificate release: implementation plan for 1A and Doc 2

Status: 1A AND DOC 2 COMPLETE IN THE WORKING TREE; uncommitted and unreleased.
Final combined check: `vendor/bin/sail composer check` passed (266 tests / 1,062 assertions,
audit, Pint and PHPStan), and `vendor/bin/sail npm run build` passed.
The phase handoffs in Doc 1, Doc 2 and the spine record delivered behavior. Original acceptance
checklists below remain the specification; unchecked historical items are not a new implementation task.

Prepared against `main` commit `a32af0d` on 2026-10-23. Recheck the current code before changing it.
This is a self-contained handoff for GitHub Copilot coding agent. Complete only the work below,
then return the result for human review. Do not continue into Docs 3-5.

## 1. Outcome and authority

Deliver two independently implementable parts on one certificate branch based on current `main`:

1. **1A: Catalog authoring usability.** Make certificate requirements understandable to author,
   edit, and review without making authors understand the nested database structure.
2. **Doc 2: People and enrollment.** Turn the agreement's existing candidate names into reusable
   external candidate records; assign certificates through agreement forms; display assignments
   on agreement show pages.

1A is not a prerequisite for Doc 2. They may proceed independently or in parallel, but their
combined result must be reconciled and verified before this release is complete. Parallel
execution is optional; this plan does not require additional agents.

The user confirmed these decisions after the existing certification documents were written:

- Candidates are external people without application logins. They are not `users`, staff,
  activity loggers, or the staff listed as delivering an activity.
- Existing name-only candidates must be preserved. Missing email is allowed during migration.
  Creating or editing a candidate through an application form requires a valid, unique email.
- This release assigns certificates and displays assignments. It does not capture evidence,
  calculate progress, endorse candidates, issue awards, or run renewal cycles.
- The certificate release is independent of `design/enterprise-restyle`. Use the components
  and visual system on `main`; reconcile styles on the design branch after certificate work merges.
- Each completed phase must update its documentation and leave an accurate handoff for the next.

Read, in order:

1. `AGENTS.md`, including any instructions supplied with the implementation task.
2. `.ai/rules/index.md` and applicable rules if that directory exists; search those rules for
   certification, candidate, agreement, and validation concerns.
3. `docs/certifications/spine.md`.
4. `docs/certifications/doc-1-catalog-foundation.md`, especially follow-up 1A.
5. This plan, `docs/form-conventions.md`, `PRODUCT.md`, and `DESIGN.md`.
6. Relevant Laravel, testing, Impeccable, and ponytail skills available in the checkout.

This plan reconciles the later user decisions with those documents. In particular, replace the
old unconditional required-email schema description with the migration/form distinction below,
and replace the unresolved Full/Phase clarification with the verified source rules in section 3.
Preserve unrelated decisions in the spine. If current code has changed materially, record the
deviation rather than blindly applying outdated file locations or duplicating completed work.

## 2. Scope and exclusions

In scope:

- Certificate authoring structure, contextual fields, grouping, renewal configuration, and rule summaries.
- Global candidate identity, safe legacy backfill, agreement roster links, and certificate enrollments.
- Candidate selection, creation, and editing from agreement forms; optional supervising coach selection.
- The minimum coach-duty administration needed to make that picker usable.
- Assignment visibility, validation, authorization, correction/removal behavior, and agreement duplication.
- Meaningful regression tests, browser verification, and phase documentation.

Explicitly out of scope:

- Activity-form candidate tagging, tool submission records, score entry, evidence uploads, attestations,
  progress services, percentages, completed-requirement counts, or retrospective activity credit.
- Endorsement, awarding, revocation workflows, expiration processing, renewal-cycle transitions, or PDFs.
- Candidate logins, staff-profile conversion, invitations, email verification, or notifications.
- Candidate merging, inferred identity matching by name, import tooling, or bulk data cleanup.
- A central candidate directory, candidate detail page, or certificate progress dashboard.
- Deliverable restructuring, scoring-tool-builder redesign, app-wide form extraction, or enterprise styling.
- New dependencies, a generic rules engine, autosave, draft/publish states, or deployment infrastructure.

Renewal **rule authoring** is already catalog functionality and is in 1A. Renewal **execution** remains Doc 4.

## 3. Verified domain rules and portable source context

Source: **NWIC Certification Guide Spring 2026**, printed pages 5-6, Requirements for Local Coaches,
Supervisor Checklist and COMET subsections. The user supplied this guide as the final authority for
NWIC requirements. The relevant facts are reproduced here so implementation does not depend on
a private chat, local attachment path, or preview. If given the guide, verify against these pages;
do not invent missing rules or replace the application's explicit coach-judgment boundary.

### Dimensions describe submissions; requirements constrain the submission set

- A tool defines categorical dimensions and their options. An individual submission later records
  one selected option for each applicable dimension, such as Phase 2 and Full review.
- A certificate requirement chooses a tool and a total target, then describes coverage and quotas
  across qualifying submissions. These are not new submissions or separate targets per dimension.
- One submission can contribute to the total, its phase's coverage, and a review/observation quota.
- Coverage means at least one qualifying submission for each selected phase.
- A quota means at least the configured number of qualifying submissions with the specified category.
- Check coverage and quotas against the same eligible submission pool. Do not first discard all
  document-only submissions merely because a separate rule requires four observation/Full submissions.
- Tool phase categories are distinct from certificate requirement stages `initial` and `renewal`.
- Preserve the existing generic tool/dimension/option schema. Do not hard-code COMET fields into
  the certificate builder or introduce a schema change merely to improve labels.

### Exact Local Coach examples

**Supervisor Checklist, initial certification:**

- Six submissions; coverage of phases 2, 3, and 4.
- At least four of the six are Full reviews: document review **and** observation.
- Recorded score guidance: 85% matching overall and 80% within each Wraparound element.
- Example distribution: Phase 2 Full, Phase 2 Full, Phase 3 Document, Phase 3 Full,
  Phase 4 Document, Phase 4 Full. This meets total, coverage, and Full-review distribution conditions,
  assuming the submissions otherwise qualify and the coach judges the required outcomes as passing.

**COMET, Local Coach initial certification:**

- Six submissions; coverage of phases 1, 2, 3, and 4.
- At least four of the six come from field observations involving face-to-face contact with
  Wraparound teams and families.
- Recorded score guidance: 85% matching overall and 75% within each key element.
- Example distribution: Phase 1 Observation, Phase 2 Document, Phase 3 Observation,
  Phase 4 Document, Phase 1 Observation, Phase 2 Observation. This meets the distribution conditions
  under the same qualification assumptions.
- Six submissions covering only phases 1 and 2 fail phase coverage, even if all six are observations.
  Six covering all phases with only three observations fail the observation quota.

The guide calls COMET's required evidence **field observations**; the Supervisor Checklist uses
**Full reviews**. Do not silently treat these labels as interchangeable. Preserve existing tool IDs,
dimension IDs, and option IDs. If an existing tool uses a `Full` option to represent COMET observation
evidence, make its meaning explicit in configuration/help and preserve stored references. Do not
silently rename or rewrite existing user-authored catalog data. New example fixtures can use clear,
tool-specific labels. The generic builder must show the selected tool's actual dimensions/options.

The guide also requires a prior COMET inter-rater reliability document review. The spine explicitly
defers automated requirement sequencing. Keep that deferral visible for Doc 3; do not implement a
dependency engine in 1A or Doc 2, and do not claim the deferred sequencing is already enforced.

**No automatic score math:** the application records numeric scores and a coach's outcome; it does
not compute inter-rater agreement or compare numeric thresholds to decide passing. Preserve threshold
notes as guidance. Preserve program-intersection counting and live computation as future Doc 3 rules.

These examples specify configuration semantics and later progress acceptance. They do not authorize
building a progress evaluator now. Use them to verify authored requirements and readable summaries.

## 4. Current implementation and files to inspect

Only catalog foundation is shipped. Relevant existing code includes:

- `app/Http/Controllers/CertificateController.php` and `CertificationToolController.php`.
- `app/Models/Certificate.php`, requirement/group/dimension-rule models, certification tools and roles.
- `resources/views/admin/certificates/partials/` and `admin/certification-tools/partials/`.
- `resources/views/components/repeater-rows*.blade.php`, `form-drawer.blade.php`, form shell/field
  components, token picker, and `resources/js/form-drawer.js` / `form-fields.js` where present.
- `app/Models/AgreementCertificationCandidate.php`: currently stores `agreement_id`, `name`,
  `program_id`, and `notes`; it is not a global person record.
- `app/Http/Controllers/AgreementController.php`, especially candidate sync and shared form data.
- `app/Http/Requests/AgreementRequest.php`, `app/Models/Agreement.php`, and `app/Models/Program.php`.
- `resources/views/agreements/partials/form-fields.blade.php` and `agreements/show.blade.php`:
  a name-list editor and a name-only display in the left summary currently exist.
- `app/Services/AgreementDuplicationService.php`: currently copies candidate name rows.
- `app/Support/Authorization/CertificationAccess.php`, `app/Models/UserCertificationDuty.php`,
  agreement/certificate policies, and scoped-authorization helpers.
- Candidate factories, agreement duplication tests, and `tests/Feature/Certification/`.
- Existing migrations creating agreement candidates and converting the old multiline text to rows.

Treat the existing candidate roster as populated data. There is no implemented global candidate,
enrollment, or progress system to migrate. Do not hard-code development row counts into migrations/tests.

Search every caller of changed relations and request fields. In particular, inspect the existing
`Activity::certificationCandidates()` relation rather than assuming it supplies evidence: the current
roster schema has no activity link. Do not wire it into progress or activity capture in this phase.

## 5. Workstream 1A: catalog authoring usability

Use Impeccable `shape` before UI implementation and `critique` after browser inspection. The agreed
structural direction is described below; do not require access to the original illustrative preview.
Use existing Bootstrap/Blade styling from `main` and the shared form's single Save/Cancel contract.

### Authoring structure

- [x] Organize the certificate form into freely navigable Basics, Initial requirements, Renewal,
  and Review rules sections. This is not a forced Next/Back wizard.
- [x] Keep a compact requirement list and one focused editor at a time. Start with an inline editor
  beneath the selected item; use an existing drawer only if complex real examples fit better there.
- [x] Start requirement creation with Activity work, Tool submissions, or Coach confirmation;
  reveal only relevant fields. Preserve backend kind values and completion-unit counting.
- [x] Put grouped requirements together beneath their all/any/set-number rule. Allow grouping where
  requirements are authored. Keep ungrouped requirements individually required; no arbitrary nesting.
- [x] Keep certificate-specific roles manageable beside role selection. Preserve any-role behavior
  and existing global roles; do not add a general global-role administration screen.
- [x] Show effective counting windows, inherited defaults, applicable role/program conditions, and
  overrides clearly. Distinguish award validity from the age of evidence that can count.
- [x] Preserve initial/renewal authoring, including genuinely different renewal requirements.
  Following initial rules is linked; copying creates independent rows. Explain the difference and
  confirm destructive replacements using components available on `main`.
- [x] Review rules must summarize every effective condition: kind, target, tool, coverage, quota,
  group mode/count, role, windows, passing requirement, and inherited renewal behavior where applicable.
  Preserve certificate scope and prerequisites. This reviews configuration, not candidate progress.

### Preserve the save and validation contract

- [x] Keep one certificate-level save transaction; section navigation and local editors must not
  save part of a certificate silently. Use buttons that cannot accidentally submit the form.
- [x] Preserve stable row IDs, original/new group references, stage membership, ordering, deletion
  markers, and all nested values through edits and validation failures.
- [x] Validate all transport fields that must survive Laravel validation, including nested `id`,
  `_delete`, and unsaved group references. Existing `group_index` links must continue working.
- [x] Scope submitted IDs to their owning certificate/tool/requirement. Reject forged cross-record IDs.
- [x] On kind/tool changes, handle incompatible fields deliberately. Do not retain contradictory
  saved rules or silently destroy authored values simply because controls became hidden.
- [x] Display validation errors in the relevant section/editor with accessible field associations.
  Provide keyboard operation and focus recovery after add/edit/remove; any reorder action needs
  a keyboard alternative. Do not add drag-only functionality.
- [x] Reopen existing certificates without losing requirements, dimensions, prerequisites, or roles.

### Acceptance

Author, save, reopen, and edit the NWIC Local Coach configuration from the spine, including the corrected
rules above. Also exercise Trainer prerequisites/role-filtered requirements, grouped alternatives, a
simple coach confirmation, initial/renewal edits in one save, linked renewal, and independent copies.
Preserve all existing catalog permissions. Do not seed demonstration certificates into real data.

## 6. Workstream Doc 2: people, roster, and enrollment

Completion handoff: [Doc 2 — People and Enrollment](doc-2-people-and-enrollment.md). The implementation
is complete in the working tree, including scoped candidate search, explicit known-email reuse, a
focused assignment editor, and duty administration on authorized admin Edit User forms. Profile editing
is password-only; the former Profile duty workflow has been removed. The original acceptance
checklist below remains the specification; the handoff records actual behavior and verification.
1A is also complete; its catalog-only handoff is recorded in Doc 1 and the spine. Docs 3–5 remain future work.

### 6.1 Data model and forward-only migration

- [ ] Create `certification_candidates` as a separate model/table, using the spine's names:
  name, email, phone, start_date, notes, active, and timestamps. Keep optional fields optional.
- [ ] Email is nullable in storage for migrated records. Normalize supplied emails consistently
  (trim/lowercase) and enforce uniqueness for non-null emails at the database and request boundaries.
  Never fabricate email addresses or use an empty string as the missing-email representation.
- [ ] Convert `agreement_certification_candidates` into roster links with
  `certification_candidate_id`, `sort_order`, and the existing agreement/program/notes fields.
  Preserve existing roster IDs and agreement-specific metadata.
- [ ] Backfill exactly one new person per legacy row, preserving the name and relationships.
  Preserve all names/relationships first. A forward correction reconciles matching legacy names
  into one shared candidate per normalized name, per the user's clarified identity rule. Do not create
  staff users, infer emails, or invent enrollments/progress.
- [ ] Stage the new foreign key as nullable for backfill, then enforce the completed roster link.
  Make sort order deterministic from existing order. Preserve names before removing a legacy column;
  update all callers before relying solely on the new relation. Do not leave two writable identities.
- [ ] Enforce one roster link per agreement/candidate. Legacy rows with equal names reference
  one shared candidate after reconciliation. Keep distinct verified-email profiles separate.
- [ ] Create `certificate_enrollments` and `CertificateEnrollment` with candidate, certificate,
  nullable sponsoring agreement, nullable supervising coach user, enrolled_at, notes, and the spine's
  status/stage/cycle vocabulary. Use database uniqueness for `(certification_candidate_id, certificate_id)`.
- [ ] New enrollments start at `NotStarted`, `initial`, cycle 1. Do not accept lifecycle fields from
  the browser. Do not set requirements-met, endorsement, award, or renewal data. Fields used solely
  by future phases can be added there; document the actual schema in the handoff.
- [ ] Add precise relations/casts/factories in the existing project style. Do not build award,
  attestation, submission, or action-log tables early just because they appear in the final spine schema.

Forward-only means new migrations, never editing already deployed migrations or rolling back the
development database. Preserve real records; do not truncate, reseed, or run destructive resets.

### 6.2 Candidate identity and email behavior

- [ ] Replace the agreement's string-name list with selection of existing visible candidates and
  creation of new candidates. Identify options by name and email where available, not name alone.
- [ ] New candidates and actual edits to candidate identity require name and valid, unique email.
  Phone/start date/global notes/active follow their optional meanings; keep the common editor compact.
- [ ] Untouched migrated candidates remain usable with null email. An unrelated agreement save,
  roster selection, or enrollment-only update must not count as a candidate identity edit and force
  every legacy row to acquire an email. An explicit candidate edit must collect it.
- [ ] Distinguish person-level fields from roster notes/program and enrollment notes/coach. Do not
  overwrite global candidate data just because the surrounding agreement form was submitted.
- [ ] Reject duplicate supplied emails with useful validation; allow explicitly selecting an existing
  visible candidate instead. Do not automatically merge identities or expose a hidden candidate's details.
- [ ] An inactive candidate may remain visible in existing assignments; do not silently remove history.
  Exclude inactive people from new assignments and explain preserved inactive selections when editing.

### 6.3 Assignment behavior and agreement form

- [ ] Add a compact Certification candidates section to create/edit agreement forms. Use existing
  components and a focused editor/drawer for candidate, certificate selection, and optional coach.
  It need not share 1A's editor implementation or wait for 1A to finish.
- [ ] Support multiple candidates per agreement and multiple certificates per candidate. Permit a
  roster candidate without an enrollment so migrated rows are preserved without invented assignments.
- [ ] A candidate/certificate pair is one global enrollment, even when the candidate appears on
  multiple agreements. Reuse an existing visible enrollment; do not duplicate it, reset its dates,
  overwrite its coach, or change sponsorship implicitly on another agreement save.
- [ ] For a new enrollment, default sponsorship to the agreement where it is assigned. Sponsorship
  is provenance, not ownership of the person's future progress. Creating an agreement must establish
  its ID before dependent rows are written, within the same save transaction.
- [ ] Offer active, non-retired, authorized certificates applicable to the agreement/roster program
  scope. Respect `All`, `Specific`, and `None` using existing scope helpers. `All` does not bypass
  authorization; `None` supplies no eligible programs. Do not silently guess a program for a legacy row.
- [ ] Preserve existing now-inactive/retired selections for display; prevent new assignment to them.
  Catalog prerequisites remain recorded configuration. Do not implement award-based eligibility checks
  before the award lifecycle exists; do not tell users those checks have already been performed.
- [ ] Save candidate creation/edits, roster changes, and enrollment changes atomically with the
  agreement. Validation errors must restore all rows, selections, local edits, and their correct editor.
- [ ] Repeated saves are idempotent. Database uniqueness must prevent duplicate roster/enrollment
  rows under concurrent requests; return a useful result/error rather than a raw constraint exception.

### 6.4 Display, corrections, and other agreement flows

- [ ] On agreement show, replace the left-hand name-only list with a compact assignment summary.
  Show each roster candidate, their applicable visible certificate names, and coach where selected.
  Include explicit empty states such as No certificates assigned. Keep long lists expandable.
- [ ] A candidate on multiple agreements may show the same applicable enrollment on each roster;
  filter by the current agreement's effective program scope and the viewer's visibility. Do not use
  sponsoring agreement as the future evidence-counting boundary or expose unrelated enrollments.
- [ ] Expandable assignment details may use a disclosure/drawer. Reuse established behavior and
  accessible names. Do not add 0%, progress bars, completed counts, award buttons, or fake evaluation.
- [ ] Distinguish removing a candidate from this agreement from deleting the person or enrollment.
  Roster removal deletes/detaches only that agreement link and preserves the candidate and enrollments.
- [ ] Allow explicit correction/removal of an unstarted certificate assignment with confirmation that
  it affects that person's enrollment wherever displayed. Require authority over the affected enrollment,
  including its sponsorship/relevant agreements. Do not delete enrollments because a payload omits them.
  Reject removal of a noninitial/non-NotStarted enrollment rather than anticipating future lifecycle rules.
- [ ] No global candidate deletion or merge UI is needed. Removing the last roster link must not
  silently destroy a candidate. Nullable sponsor references survive agreement deletion without cascade
  deletion of the candidate/enrollment; preserve existing agreement deletion authorization.
- [ ] Extend certificate deletion protection to enrollments: an assigned certificate is retired rather
  than deleted, even if it has no requirements. Do not allow catalog deletion to cascade away assignments.
- [ ] Agreement duplication reuses person IDs in new roster links, preserving applicable roster metadata.
  It must not clone people, duplicate enrollments, change their sponsors, or restart cycles.
- [ ] Fix candidate references in agreement loading, name suggestions/search, duplication, factories,
  and tests. Do not leave the old unrestricted name-suggestion query exposing cross-scope candidate data.

### 6.5 Coach selection and minimum duty administration

- [ ] Supervising coach is optional. The release must remain usable when no coaches are configured.
- [ ] Eligible coaches are active staff with an active coach duty in the enrollment's relevant program
  scope and the visibility needed for the agreement. Validate eligibility server-side as well as filtering
  the picker. Duty does not grant implicit visibility or substitute for agreement/certificate permission.
- [ ] Reuse `CertificationAccess` and existing system/project/program scopes. For a certificate applicable
  to all programs, establish coach eligibility against the assignment's actual program context rather
  than treating a narrow program duty as global. Do not change the staff reporting hierarchy.
- [ ] Add the smallest grant/revoke control for coach duties to an existing authorized user-admin surface
  if no usable control exists. Follow `canGrantDuty`, retain revoked rows, record actor/timestamps, and
  preserve existing service-level authority. Profile editing remains password-only; do not add a Profile
  duty workflow. Validate revocation authority too. Do not build a new duty-admin subsystem.
- [ ] Scope manager-duty workflow/UI to Doc 4 unless already implemented. Record this narrowing of the
  old general duty-UI note in the handoff. Never seed or silently grant duties to populate the picker.

### 6.6 Authorization and visibility

- [ ] Existing agreement view/update permissions gate reading and changing its roster/assignments.
  Read-only agreement viewers may see applicable assignment summaries without receiving catalog edit
  rights. Preserve the current admin-only certificate catalog policy.
- [ ] Candidate lookup is limited to candidates reachable through agreements the actor may view;
  system administrators may manage otherwise unlinked migrated/orphaned candidates. Do not add a
  globally searchable public person directory or allow submitted IDs to bypass lookup boundaries.
- [ ] Shared identity edits affect every linked agreement. Permit them only when the actor can manage
  the affected linked agreements, or is a system administrator. A user with narrower access can still
  select an authorized existing candidate without silently editing their global name/email.
- [ ] Enrollment mutations must be authorized against their certificate scope and relevant existing
  assignment context, not just a guessed enrollment ID or any agreement the actor happens to edit.
- [ ] Revalidate ownership of roster rows and all submitted candidate/certificate/coach/program IDs.
  Prevent changing another agreement's rows through nested IDs. Preserve CSRF and escaped output.
- [ ] New duties compose with existing access; do not add coach/manager concepts to `UserAccess`,
  `AccessProfile`, or `PrivilegeCapability`. Make only necessary policy/relation extensions in the
  existing authorization architecture, and explain them in the handoff.
- [ ] Eager-load roster people, enrollments, certificates, and coaches. Avoid per-candidate queries
  or Blade database lookups on agreement pages and forms.

## 7. Regression coverage and browser acceptance

Read the testing-best-practices skill and nearby tests before choosing coverage. Test behavior,
not implementation strings. Use factories in the isolated `testing` database; no test data in the
development database. Read `phpunit.xml`/`tests/Pest.php` and verify isolation before running tests.

Add/update focused tests for:

- [ ] 1A save/reopen preservation of groups, unsaved group references, IDs, ordering, both stages,
  dimension rules, prerequisites, linked renewal, independent copies, and validation-error values.
- [ ] Both corrected tool examples are expressible, persist their intended configuration, and produce
  accurate summaries. Include non-NWIC tool dimensions so the authoring UI remains generic.
- [ ] Server rejection of dimension/option/role IDs from the wrong tool/certificate and invalid groups.
- [ ] Legacy migration preserves every name/roster relationship, permits null emails, and does not merge
  names before reconciliation or create enrollments. Test the forward legacy-name reconciliation and
  preservation/conflict behavior separately. Exercise the initial backfill with an isolated legacy-schema fixture;
  never roll back the real database to construct the test.
- [ ] Candidate create/edit email validation, normalization/uniqueness, and successful completion of a
  migrated person's profile. Unrelated agreement/assignment saves preserve untouched null-email people.
- [ ] Agreement create/update assigns multiple candidates/certificates, reuses existing candidates and
  enrollments, preserves enrollment identity/sponsorship, and handles repeated submissions correctly.
- [ ] Transactional failure leaves no partial candidate, roster, or enrollment writes.
- [ ] Assignment display and empty states; no progress UI, activity credit, or lifecycle side effects.
- [ ] Roster removal, explicit unstarted-enrollment removal, agreement deletion, and duplication obey
  the preservation rules above. Database uniqueness protects repeated/concurrent insertion attempts.
- [ ] Deleting a certificate referenced only by an enrollment retires it and preserves the assignment.
- [ ] Authorization: unauthenticated/read-only users, cross-program certificate choices, hidden candidate
  lookup/IDs, cross-agreement roster IDs, shared identity edits, and ineligible/revoked coaches.
- [ ] Coach grant/revoke scope limits, self-grants where authorized, and retention of revoked duty rows.
- [ ] Existing certificate, agreement, program-scope, duplication, and other affected tests still pass.

Browser acceptance through the repository's Playwright MCP workflow:

- Inspect certificate create/edit and agreement create/edit/show at desktop and phone sizes with
  long labels, several candidates, several certificates per candidate, and empty/migrated cases.
- Verify create/edit/save/reopen, validation errors and restored editors, main-column assignment details,
  keyboard operation, focus return, Escape, unsaved changes, and no accidental submits or nested forms.
- Verify a read-only viewer gets summaries without unauthorized edit links or actions.
- Capture screenshots in `.artifacts/` only, at most one before/after pair per changed component;
  do not commit them. Read recent browser errors/logs and resolve errors caused by the change.
- Run Impeccable critique on the resulting UI, followed by one bounded correction pass as needed.

If the execution environment lacks the required browser integration, report precisely what could
not be checked. Do not claim browser acceptance from HTML-string assertions. Do not install new
browser/test dependencies or substitute a hand-built verification framework without approval.

## 8. Commands, checks, and environment constraints

Use Laravel Sail for PHP, Artisan, Composer, and Node commands. Confirm installed package versions
before relying on version-specific APIs; use Boost `search-docs` when available, with an official
installed-version documentation fallback when it is not. Follow current repository commands.

Useful discovery/setup commands in a configured checkout:

```sh
vendor/bin/sail composer show --direct
vendor/bin/sail artisan route:list --except-vendor
vendor/bin/sail artisan migrate:status --no-interaction
vendor/bin/sail artisan migrate --pretend --no-interaction
```

Create Laravel files through the appropriate `artisan make:*` command with `--no-interaction`;
check command help rather than guessing arguments. Use forward `migrate` only when the target
database is understood. Inspect schema/read-only data through Boost tools when available.

During development, run the affected Pest files after behavior changes, for example:

```sh
vendor/bin/sail artisan test --compact tests/Feature/Certification
vendor/bin/sail artisan test --compact tests/Feature/AgreementDuplicateTest.php
```

Include new Doc 2 tests and other affected tests in the actual command list. Format modified PHP
before final checks:

```sh
vendor/bin/sail bin pint --dirty --format agent
```

The repository's required final aggregate check is:

```sh
vendor/bin/sail composer check
```

At the planning baseline this runs dependency installation, audit, Pint check, PHPStan, and tests.
Inspect resulting changes; do not include unrelated dependency/lockfile churn. Also run the frontend
build for UI changes and the whitespace check:

```sh
vendor/bin/sail npm run build
git diff --check
```

Follow `AGENTS.md`'s fallback check order if `composer check` is no longer defined. Never claim a
check passed if it did not run. Resolve regressions; if infrastructure prevents execution, report
the exact command/error and what remains unverified. Do not copy a success count from an old handoff.

The planning worktree initially had no `vendor`/Node dependencies. A cloud agent must use its own
configured checkout and isolated test services; a host's local Sail container does not automatically
mount another worktree. Do not point test resets at the development database or reuse a conflicting
Compose project/volume merely to make commands work. Do not print populated environment secrets.

Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, destructive seeds, or truncation
against development/shared data. Verify any test database recreation is confined to disposable testing.

## 9. Documentation deliverables and reconciliation

At implementation start, reconcile the spine's email language and Full/Phase clarification with
sections 1 and 3 of this plan. Keep Doc 1 marked SHIPPED; 1A has its own status. Do not claim planned
work is complete while editing those decisions.

As each part actually completes:

- [ ] Update 1A in `doc-1-catalog-foundation.md` with completion status, the implemented interaction,
  confirmed rule semantics, meaningful deviations, and verification evidence.
- [ ] Create `docs/certifications/doc-2-people-and-enrollment.md` as the durable Doc 2 implementation/
  handoff document. Record actual tables, relations, routes, policies, request shapes, email behavior,
  roster/enrollment removal semantics, coach-duty controls, and tests/checks. This plan explicitly
  authorizes that phase document; do not create a separate general documentation hierarchy.
- [ ] Update the spine's authoritative schema and terminology to reflect nullable legacy emails,
  global candidates, roster links, actual enrollment fields, and the corrected tool-specific wording.
- [ ] Append separate 1A and Doc 2 handoff entries with what shipped, actual names, deviations,
  commands/results, and unresolved constraints. Record any intentionally deferred requirement sequencing.
- [ ] Record proven authoring conventions in `docs/form-conventions.md`. Replace the old description
  of candidates as string lists where it no longer applies; do not imply all unrelated forms were rebuilt.
- [ ] Keep Docs 3 and 4 planned. State that there is no migrated progress, automatic credit,
  endorsement, award, or renewal execution yet. Preserve future program-intersection/live-computation rules.
- [ ] Reconcile both workstreams' shared components, validation transport, naming, authorization, and
  documentation. Avoid parallel form implementations or premature extraction for deliverables.

The next reviewer must be able to understand the implementation from the diff and these documents
without this chat. Include screenshots/check results in the review handoff through supported artifact
links, while keeping generated screenshots out of Git.

## 10. Completion and return for review

Before handing back:

- [ ] Every in-scope acceptance item above is implemented and verified, or explicitly identified as
  blocked with evidence. Do not quietly drop a requirement or expand into the next release phase.
- [ ] Run ponytail-review on the combined diff once; label findings BLOCKER / SHOULD-FIX / NIT.
  Fix BLOCKERs, apply the cuts you agree with, and list those cuts. At most one re-review.
- [ ] Report files/areas changed, migration and data-preservation behavior, all checks with actual
  pass/fail/skipped results, browser evidence, skills used, deviations, and remaining risks.
- [ ] Leave the work ready for the user's review here. Follow the implementation task's Git rules;
  this document does not authorize pushing to `main`, deploying, bypassing hooks, or publishing work.

Suggested instruction when assigning the GitHub Copilot task:

> Implement `docs/certifications/implementation-plan-1a-and-2.md` on a certificate branch from current
> main. Complete 1A and Doc 2 only, using the plan's confirmed requirements and repository instructions.
> Preserve migrated name-only candidates with nullable email; require email for candidate create/edit.
> Deliver assignment and agreement display without progress or lifecycle work. Update the phase docs,
> run the required checks, and return the combined result for review with evidence and deviations.

Before launching the remote task, ensure this plan exists in a repository revision that the agent can
read. An uncommitted local file or private T3 attachment is not a portable handoff by itself.
