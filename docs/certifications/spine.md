# Certification Feature — Shared Spine

Read this FIRST in every certification execution conversation, then read only your own doc
(`docs/certifications/doc-N-*.md`). Append a handoff to the bottom of this file when your doc ships.

## RUNNING COMMANDS — READ THIS FIRST
- **This project uses Laravel Sail.** Prefix everything:
  `./vendor/bin/sail artisan ...`, `./vendor/bin/sail composer ...`, `./vendor/bin/sail npm ...`,
  `./vendor/bin/sail test`. A bare `php artisan` targets the wrong environment.
- **NEVER run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, or `db:wipe`.**
  The development database holds real work. Migrations are FORWARD-ONLY. If a migration is wrong,
  write a new corrective migration — do not roll back and do not recreate the schema.
- Do not seed over existing data. Do not truncate tables.
- Safe to run freely: `migrate`, `migrate --pretend`, `migrate:status`, `test`, `view:clear`,
  `config:clear`, `route:list`.

## What this feature is
External people ("candidates") pursue certificates. A certificate is a program-scoped catalog entry whose
requirements are satisfied by (a) counted activity work, (b) scored tool submissions, or (c) manual
attestation. A national coach records and judges work; a certification manager awards. Progress is computed
live, scoped by program intersection.

## Non-negotiable decisions
1. **The system NEVER does score math.** COMET / Supervisor Checklist matching percentages are INTER-RATER
   AGREEMENT computed in InnovatePractice(c) from two raters' sheets we do not have. We store the coach's
   pass/fail verdict plus the numbers for the record. Thresholds (85%/80%/75%) are DISPLAY-ONLY notes on a
   requirement. Never compared against. Client stated this twice.
2. **Progress is scoped by PROGRAM INTERSECTION, never by agreement.** Certificate programs ∩ activity
   programs. FOCUS work cannot credit a Wraparound certificate. Agreement transfer is therefore a no-op —
   no transfer tooling exists or is needed. `agreement_id` on a tag row is PROVENANCE/display only.
   CAVEAT: `program_scope_mode = All` means no containment; default new certificates to `specific`.
3. **Progress is COMPUTED, NOT MATERIALIZED.** Deliberately diverges from `DeliverableContributionService`.
   Rolling windows change what counts with no write to trigger a rebuild, so a materialized table would
   drift silently. Also makes activity deletion self-correcting.
4. **Duties live OUTSIDE the privilege hierarchy.** admin/view are access LEVELS; coach/manager are
   orthogonal DUTIES. `PrivilegeCapability`, `UserAccess`, `AccessProfile` and every existing policy stay
   UNTOUCHED. They compose: awarding requires VIEW privilege (visibility) AND manager duty in scope
   (action). A duty NEVER grants implicit visibility.
5. **Tags and submissions are separate.** A TAG = this candidate was involved in this activity, in this role
   (unique per activity+candidate). SUBMISSIONS hang off a tag, zero or many. A training has a tag and no
   submissions; a coaching session where two checklists were scored for one person has one tag, two
   submissions.
6. **The rolling window IS the lapse policy.** No separate lapse handling — old work simply falls out.
7. **Never auto-revoke an award.** Revocation is manual, requires a written reason, and is logged.
8. **Snapshot requirements onto the award row at award time** so later catalog edits cannot invalidate an
   issued certificate.
9. **Coach endorsement is a HARD GATE.** requirements met (system) → coach endorses → manager awards.
10. Per-site "criteria may be adapted" overrides: client said IGNORE. Out of scope.

## Terminology
- **Candidate** — an external person. No login. Global record, persists across agreements. Email is
  required and unique whenever a form creates or edits a candidate's identity; it is nullable in
  storage so legacy migrated rows can exist without one (Doc 2 correction — superseding the earlier
  unconditional "Email REQUIRED" in this line).
- **Certificate** — catalog entry, program-scoped, optional expiry, optional prerequisites.
- **Requirement** — one line item. Kind = activity_count | tool_submission | attestation.
- **Requirement group** — satisfy_mode all | any | n_of, for "complete 3 of these 5".
- **Phase** — `initial` or `renewal`. Both sets live in the same tables and the same form. Renewal may
  introduce genuinely new requirements, not just lower targets.
- **Tool** — a scoring instrument (COMET, Supervisor Checklist, SAS, CREST, STEPS), built in a TOOL BUILDER.
  Separate entity, NOT an activity type.
  - **Dimension** — categorical, characterizes a submission. COMET: Phase (1-4), Review Mode (Document|Full).
    "Full review" = document review AND observation. CREST has no dimensions.
  - **Score field** — numeric, RECORDED ONLY. COMET: Overall Match %, Element Match %.
    SAS: Total (of 12), Coaching, Communication, Analysis. Default a new tool to one field named "Score".
  - **Submission** — one coordinate across the tool's dimensions + score values + coach-judged outcome.
- **Enrollment** — (candidate, certificate). Carries status, phase, cycle, supervising coach.
- **Award** — one issued credential. Multiple over time via renewal cycles.
- **Duty** — coach | manager, scoped system/project/program.

## Requirement evaluation order (ORDER MATTERS)
attestation      → `certificate_attestations` lookup for (enrollment, requirement, cycle).
activity_count   → 1. filter tags: candidate; activity NOT cancelled, NOT not_yet_complete;
                      contact_family/activity_type match; certification_role match;
                      PROGRAM INTERSECTION (skipped when cert scope = All);
                      engagement_date >= now − window (requirement override else certificate default);
                      cycle > 1 → engagement_date > previous award date
                   2. sum completion_units, compare to target_count
tool_submission  → 1. same base filters, plus certification_tool_id match
                   2. if requires_passing → keep only outcome = passed
                   3. independently evaluate rules over that same eligible/passing pool:
                      coverage = at least 1 submission per listed option;
                      quota    = at least min_count submissions matching any listed option
                   4. count the same pool for target_count; dimension rules do not reduce it
                   Score values are NEVER read by the engine.
Group eval: all | any | n_of. Overall = all groups + all ungrouped requirements satisfied.

## Acceptance test — the NWIC Local Coach certificate
If the model cannot express this table, the design is wrong. Source: NWIC Certification Guide Spring 2026.

**Confirmed from the guide:** a submission can count toward its total, phase coverage, and review quota
at once. Supervisor Checklist requires six, phases 2–4 and at least four Full reviews (document plus
observation). COMET requires six, phases 1–4 and at least four field observations with face-to-face
teams/families. Do not filter the entire pool to Full/observations before counting the six. Preserve
existing tool option IDs and make their observation meaning clear when configuring COMET. Thresholds
remain coach guidance, not calculated score gates. See section 3 of the implementation plan for source
context and the explicitly deferred COMET inter-rater sequencing prerequisite.

| Guide requirement | Kind | Encoding |
|---|---|---|
| Registration in InnovatePractice(c) | attestation | coach checks off |
| Wraparound 101/102/201/401/402/501 | activity_count ×6 | target 1 each, by activity type |
| CREST — 12 submissions | tool_submission | tool with no dimensions, target 12 |
| Supervisor Checklist — 6, span phases 2-4, ≥4 full reviews, 85%/80% | tool_submission | target 6, requires_passing, coverage Phase{2,3,4}, quota ReviewMode=Full min 4, threshold note |
| STEPS — "ability to move from expert practitioner…" | attestation | unmeasurable by design |
| COMET — 6 across all 4 phases, ≥4 field observations | tool_submission | coverage Phase{1,2,3,4}, quota field observations min 4 (an existing Full option must represent this meaning), target 6, requires_passing |
| SAS — 9/12 across 3 sessions, 75% per section | tool_submission | target 3, four score fields, coach judges |
| Participate in all NWIC coaching sessions | attestation | no fixed target |
| WVCC — 3 times over a year | activity_count | target 3, window_months = 12 |
| Trainer: attend/observe/co-train/be-observed × W101,W102 | activity_count ×8 | role-filtered |
| Trainer requires Coach OR Supervisor | prerequisite | prerequisite_mode = any |
| Annual recertification at reduced targets | phase = renewal | same tables, same form |

DEFERRED: requirement sequencing (COMET inter-rater review must precede COMETs counting). Appears once in
the guide. Add `depends_on_requirement_id` later if asked.

## Schema (authoritative)
certification_candidates          name, email (nullable in storage — legacy-backfilled rows may have
                                  none; NORMALIZED trim+lowercase, unique when present; required +
                                  valid + unique whenever a form creates or actually edits a
                                  candidate's identity), phone, start_date, notes, active. SHIPPED
                                  (Doc 2) exactly as described here — the schema below is as-built,
                                  not merely planned.
agreement_certification_candidates (EXISTS, becomes roster link) — IMPLEMENTED (Doc 2)
                                  + certification_candidate_id FK (not null, restrictOnDelete),
                                  + sort_order; keeps agreement_id, program_id, notes; the legacy
                                  `name` column was dropped after backfill. Backfilled one person per
                                  row initially; the forward legacy-name reconciliation then unifies
                                  matching legacy names and repoints their roster/enrollment links.
                                  unique(agreement_id, certification_candidate_id).
certificates                      name, description, active, sort_order, program_scope_mode, retired_at,
                                  validity_months nullable (null = never expires),
                                  default_window_months nullable (null = all-time),
                                  prerequisite_mode (all|any)
certificate_program               pivot via ScopeSync
certificate_prerequisites         certificate_id, required_certificate_id
certificate_requirement_groups    certificate_id, phase, label, satisfy_mode, required_count nullable,
                                  sort_order
certificate_requirements          certificate_id, certificate_requirement_group_id nullable, phase, kind,
                                  contact_family_id nullable, activity_type_id nullable,
                                  certification_tool_id nullable (required when kind=tool_submission),
                                  certification_role_id nullable, target_count, requires_passing,
                                  threshold_note (DISPLAY ONLY), window_months nullable, label, notes,
                                  sort_order
certificate_requirement_dimension_rules
                                  certificate_requirement_id, certification_tool_dimension_id,
                                  mode (coverage|quota), option_ids json, min_count nullable
certification_tools               name, slug, description, active, sort_order, program_scope_mode, retired_at
certification_tool_program        pivot via ScopeSync
certification_tool_dimensions     certification_tool_id, name, slug, sort_order
certification_tool_dimension_options
                                  certification_tool_dimension_id, label, value, sort_order
certification_tool_score_fields   certification_tool_id, name, slug, unit (percent|points|number), sort_order
certification_roles               name, slug, certificate_id nullable (null = standard/global), active,
                                  sort_order. NO activity_type whitelist — role options on the log derive
                                  from REQUIREMENTS matching that activity's classification.
certificate_enrollments           IMPLEMENTED (Doc 2) with only the fields that pass currently writes:
                                  certification_candidate_id, certificate_id, sponsoring_agreement_id
                                  nullable (nullOnDelete — provenance only, survives agreement
                                  deletion), supervising_coach_user_id nullable (nullOnDelete),
                                  status, phase, cycle, enrolled_at, notes.
                                  unique(certification_candidate_id, certificate_id). Doc 4 adds
                                  requirements_met_at/endorsed_at/endorsed_by_user_id/award fields via
                                  a NEW migration when actually needed — they do not exist yet.
certificate_awards                certificate_enrollment_id, cycle, phase, awarded_at, awarded_by_user_id,
                                  expires_at, requirements_snapshot json, revoked_at, revoked_by_user_id,
                                  revoke_reason, pdf_path, pdf_generated_at
certificate_attestations          certificate_enrollment_id, certificate_requirement_id, cycle, satisfied,
                                  satisfied_at, satisfied_by_user_id, note, evidence_file_path
                                  unique(enrollment, requirement, cycle)
activity_certification_candidate  activity_id, certification_candidate_id, agreement_id nullable
                                  (PROVENANCE ONLY), certification_role_id nullable, completion_units, notes
                                  unique(activity_id, certification_candidate_id)
certification_tool_submissions    activity_certification_candidate_id, certification_tool_id, outcome
                                  (passed|not_passed|not_scored), recorded_by_user_id, notes
certification_tool_submission_dimension_values
                                  certification_tool_submission_id, certification_tool_dimension_id,
                                  certification_tool_dimension_option_id
                                  unique(submission, dimension)
certification_tool_submission_scores
                                  certification_tool_submission_id, certification_tool_score_field_id,
                                  value decimal. unique(submission, score_field)
certificate_action_logs           certificate_enrollment_id, certificate_award_id nullable, user_id
                                  nullable, action, context json, reason text; created_at only
user_certification_duties         user_id, duty (coach|manager), scope_type (system|project|program),
                                  scope_id nullable, granted_by_user_id, created_at, revoked_at,
                                  revoked_by_user_id. Self-auditing; no separate audit table.
                                  Admins grant within their own scope. SELF-GRANT ALLOWED and logged.

## Existing patterns to mirror (verified)
- Scoped catalog CRUD: `ContactFamilyController`, `ActivityTypeController`. Uses `ScopeSync::applyTo()`,
  `ProjectProgramScope::validateModeSelection()` / `validateScopedAssignments()`,
  `ScopeSync::validateSubmittedProgramsAreInAdminScope()`. HTMX partial index (`HX-Request` header).
- Policies: `app/Policies/Concerns/AuthorizesScopedEntity.php`. Policies are AUTO-DISCOVERED by naming
  convention — no registration needed. `Gate::before` in `AppServiceProvider::registerAuthorization()`
  lets system admins bypass everything except `UserPolicy`.
- Model traits: `HasProgramScope` (needs a `programs()` BelongsToMany; gives `->projects` accessor),
  `VisibleToUser` (gives `scopeVisibleTo`; MUST add new model classes to its match statement).
- Enums: `ProgramScopeMode` (all|specific|none). `DeliverableStatus` shows the label()/icon()/tone() pattern.
- Views: `resources/views/admin/{entity}/{index,create,edit}.blade.php` +
  `partials/{filters,table,form-fields}.blade.php`. Shared form-fields infers edit mode from `isset($model)`.
- Components: `x-project-program-scope-picker`, `x-token-picker`, `x-form-drawer`, `x-form-field`,
  `x-form-shell`, `x-form-switch`, `x-form-subsection`, `x-section-card`, `x-page-header`, `x-save-bar`,
  `x-table-sort-link`, `x-table-badge-list`, `x-htmx-pagination`, `x-status-badge`.
- Repeaters: `x-inline-string-list` (ONE value per row; names `{name}[{row_key}][{valueField}]` + `id` +
  `_delete`). `x-participant-time-rows` (MULTI-FIELD; `<template>` clone + JS; names
  `participant_times[__INDEX__][field]`). Deliverables drawer:
  `agreements/partials/deliverables-section.blade.php` = visible table + hidden inputs + drawer editor.
- Audit: `ActivityActionLogService::record()`; `ActivityActionLog` has `created_at` only (no `updated_at`);
  `ActivityAction` enum with `label()`; rendered by `activities/partials/action-log-list.blade.php`.
- Files: `PrivateFileService::store()/serve()/deleteIfExists()`; `config/uploads.php`; blocked MIME list;
  S3 temporary signed URLs; `agreements.attachments.download` route uses `->scopeBindings()`.
- `ActivityController::store()` L264-398 / `update()` L437-598 — `DB::transaction`, sync order:
  duration snapshot → logging answers → agreements → states → orgs → programs → participants →
  funding sources → time tracking → `deliverableContributionService->syncForActivity()` → action log.
  `destroy()` L611-618 does NOT clean deliverable contributions; our live computation avoids that bug class.
- Routes: all in `routes/web.php` under `Route::middleware(['auth','active'])`. No admin prefix —
  authorization is policy-driven. `Route::resource(...)->except(['show'])` is the catalog convention.
- `composer.json`: NO PDF library. Queue driver = database. ZERO Notification classes exist today.
- `User.is_supervisor` / `supervisor_id` is the STAFF reporting hierarchy — UNRELATED to certification
  coaches. Do not conflate.

## Known gotcha
Stale/corrupted compiled Blade views in `storage/framework/views/` produce impossible "undefined variable"
errors. Fix with `./vendor/bin/sail artisan view:clear`. See the repo memory `domain-facts.md` for more detail.

## Document sequence
1. Catalog foundation — enums, duties + CertificationAccess, tool builder, roles, certificates +
   requirements. Acceptance: author the NWIC Local Coach certificate. No people, no progress. **SHIPPED** —
   see `doc-1-catalog-foundation.md`.
   **Follow-up 1A — Catalog authoring usability: COMPLETE IN WORKING TREE**, specified in that document's
   follow-up section and the handoff below. Uncommitted, independently verified alongside Doc 2; the
   guide rules and authoring pattern are ready for Doc 3. Independent of the enterprise restyle.
2. People & enrollment — candidates person table + backfill, agreement drawer, coach picker.
   **COMPLETE IN WORKING TREE; UNCOMMITTED** — see `doc-2-people-and-enrollment.md`.
3. Capture & progress — activity-form tagging + submissions, attestation UI, CertificateProgressService.
4. Lifecycle — statuses, endorsement gate, award/revoke, expiry + renewal cycles, action log.
5. Surfacing (deferrable) — central indexes, candidate detail, PDF generation
   (needs `barryvdh/laravel-dompdf`), expiration notifications.

## Certificate release and design integration
- Certificates must be releasable before and separately from `design/enterprise-restyle`. Implement
  certificate work on a branch based on `main`, using the components and visual system available there.
  The follow-up improves authoring structure and behavior; it does not import the restyle's CSS or shared
  component changes, or depend on its new confirmation dialog.
- After certificate work reaches `main`, merge the updated `main` into the design branch and reconcile
  certificate templates with the newer styles there. Keep one certificate implementation; avoid parallel
  versions or repeated file copying. The later design release carries the integrated certificate work.
- Use the certificate builder to establish a possible form pattern, then evaluate it against a difficult
  deliverable. App-wide form changes and deliverable restructuring are outside this certificate release.

## Still open (Doc 5 TODOs, low risk)
- PDF content / template / whether to regenerate after a name correction.
- Notification recipients, lead time, repeat cadence.

## Handoffs
(append one terse entry per shipped doc: what landed, actual names, deviations, impact on later docs)

### Doc 1 — Catalog Foundation (SHIPPED)
All phases A-H complete and verified. Migrations run via `./vendor/bin/sail artisan migrate` (clean,
no errors). Full test suite has 3 pre-existing UNRELATED failures in `tests/Feature/LoggingFieldFlowTest.php`
(references a dropped `users.role` column removed by `2026_08_27_150000_add_access_profiles_and_user_privileges`
migration; the test was never updated) — confirmed via `git status` that this file predates and is untouched
by Doc 1 work. Not fixed (out of scope, pre-existing, user prefers not touching tests unless asked).

**Names — exactly as planned, no deviations**: all 10 models, `CertificationAccess`, 3 policies,
`CertificationToolController` + `CertificateController`, routes `certification-tools` / `certificates`
(both `Route::resource(...)->except(['show'])`), nav section "Certification Setup" in `AppNav::adminSections()`.

**Two necessary additive touches to shared authorization code** (NOT a violation of "UserAccess stays
untouched" — that rule is about not adding coach/manager duty concepts into the privilege hierarchy; this is
the standard extension every new scoped-entity catalog requires, identical to how ContactFamily/ActivityType
were wired in):
- `VisibleToUser::scopeVisibleTo()` match statement: added `CertificationTool::class, Certificate::class` to
  the `applyScopedEntityVisibility` arm.
- `UserAccess::canViewRecord()` match statement: added explicit arms for `CertificationTool` and `Certificate`
  (same `hasAdmin() && applyScopedEntityVisibility(...)->exists()` pattern as ContactFamily/LoggingField/
  ActivityType). Without this, policy `view()` always returns false (falls to `default => false`).
  **Any future new scoped-entity catalog needs both of these arms added, or authorization silently fails.**

**Slug/value auto-generation**: added `Str::slug()` boot-time generation (mirroring `LoggingField`) to
CertificationTool, CertificationToolDimension (slug from name), CertificationToolDimensionOption (value from
label), CertificationToolScoreField, CertificationRole. `Certificate` has no slug column (name unique only).

**Nested repeater form pattern — new `x-repeater-rows` component** (`resources/views/components/repeater-rows.blade.php`):
generalizes `x-participant-time-rows`'s `<template>`-clone approach for **arbitrarily nested, multi-instance**
repeaters (dimensions→options; requirement_groups + requirements→dimension_rules), which the single-instance
ID-based original couldn't do. API: `:name`, `:rows` (existing data), `:next-index` (optional override — needed
when a page has two repeaters sharing one flat array with an index offset, see below), `:index-token` (default
`__INDEX__`, override per nesting level e.g. `__OPT_INDEX__` to avoid collision when the outer template's
string-replace runs), `:template` (raw HTML string, typically `view(...)->render()` of a row partial with the
index set to the token), default slot = pre-rendered existing rows. Mechanics: direct-child `[data-repeater-rows]`
container + direct-child `<template data-repeater-template>` + `[data-repeater-add]` button, all found via
`:scope`-equivalent direct-children lookup (NOT global querySelector) so nesting works. Delete is soft: existing
rows (`data-existing="1"`) get hidden via a `data-repeater-delete` hidden input set to 1 + `display:none`, never
stripped from the DOM (so `_delete` submits). New rows are just removed from the DOM. Uses a single delegated
`document` click listener (`@once`), so it self-wires content added by other JS (e.g. the certificate form's
kind/mode/dimension toggle script) with no extra plumbing.

**Certificate form's phase split**: `requirement_groups[]` and `requirements[]` are FLAT arrays shared across
both Initial/Renewal tabs (matches the schema — phase lives on the row, not the array). Each tab renders its
OWN `x-repeater-rows` instance filtered to its phase via `collect(...)->filter(...)` (which preserves original
array keys), and the **Renewal tab's `x-repeater-rows` `:next-index` is offset by +1000** from the Initial tab's
next index so simultaneous additions in both tabs before submit can't collide (PHP doesn't need contiguous
array keys, so gaps are harmless). `requirements[].group_index` stores the **array key of the referenced
`requirement_groups[]` row** (not its DB id) precisely so a brand-new group and a brand-new requirement can be
created in the same submission and get wired together; the controller does a two-pass sync
(`syncRequirementGroups()` returns `[submittedIndex => savedGroupId]`, then `syncRequirements()` resolves it).

**Dimension-rule authoring UI simplification (deviation, low risk)**: rather than cascading AJAX based on the
row's chosen tool, ALL tools' dimensions are pre-rendered as `<optgroup>`s in one select, and ALL dimensions'
option-checkbox-groups are pre-rendered (hidden) per dimension-rule row, toggled by a plain `change`-delegated
JS show/hide keyed on the selected dimension id. Fine at catalog scale (a handful of tools/dimensions); would
need revisiting if the tool catalog grows large.

**Validation gotcha — found and fixed twice, will recur in Docs 2-5**: `Validator::validate()` (and
`->validated()`) STRIPS any submitted key that has no corresponding rule, even wildcard array rows. Missed
`requirements.*.group_index` on the first pass (group linking silently broke — requirements saved with
`certificate_requirement_group_id = null` despite a group being selected) and missed `*.id` / `*._delete` on
every repeater (`roles`, `requirement_groups`, `requirements`, `dimension_rules`, and the tool's `dimensions` /
`dimensions.*.options` / `score_fields`) — editing/deleting existing nested rows would have silently no-opped.
**Rule of thumb for every future nested-repeater controller: every submitted field name, including `id` and
`_delete` and any cross-referencing index field, needs an explicit (even if just `['nullable']`) validation
rule, or it vanishes from `$validated` and the sync method silently treats it as absent.** Caught via a direct
controller-level smoke test (constructed a `Request` with nested array payloads and called `store()`/`update()`
directly in tinker) rather than a browser click-through — recommend the same technique for Docs 2-5 since there
is no browser tool available in this environment.

**Verified via direct smoke tests (tinker), not a browser** — all cleaned up afterward, no lingering test data:
- Authored COMET/Supervisor Checklist/SAS/CREST tools and the full NWIC Local Coach certificate (initial +
  partial renewal) exactly per the acceptance table above; every row was expressible.
- Authored a Trainer certificate with `prerequisite_mode=any` over Coach + a placeholder Supervisor certificate,
  plus 4 certificate-scoped roles (Attend/Observe/Co-Train/Be Observed) × 2 activity types = 8 role-filtered
  activity_count requirements.
- Program-scoped admin boundary confirmed for real: a user administering only program 6 got `false` from
  `canUpdateScopedRecord` on NWIC-scoped (program 3) tools/certificates; the system admin succeeded. Both
  `create()`/`edit()` views render without error for a system admin, including with fully-populated nested data.
- Duty grant (program-scope coach, self-granted system-scope manager), `coachesProgram()`/`isManager()`
  reflecting it, and **revocation-retention** (row stays in DB with `revoked_at` set; `isCoach()` correctly
  flips to false) all confirmed directly against `CertificationAccess`.
- `destroy()` retire-instead-of-delete guard confirmed for both a tool referenced by a requirement and a
  certificate with requirements (both get `retired_at` set + `active=false`, row NOT deleted); an unreferenced
  tool hard-deletes normally.

**No standalone UI for granting/revoking `user_certification_duties` or managing GLOBAL
(`certificate_id = null`) `certification_roles` was built in Doc 1** — the doc's Phase F only scoped
`CertificationToolController` + `CertificateController`. Certificate-SPECIFIC roles ARE manageable (nested
repeater in the certificate form). Duty granting was verified directly against the model/`CertificationAccess`
per above. **Doc 2 ("People & enrollment") should add the duty-grant UI**, likely alongside the coach picker
it already needs; a global-role admin screen can piggyback on the same catalog CRUD pattern if ever needed.

**Nothing else discovered that changes Docs 2-5** — schema, terminology, and evaluation order in this spine
all held up against the acceptance table with no changes needed.

### Doc 2 — People and Enrollment (COMPLETE; REVIEWED, UNCOMMITTED)

Branch: `feature/certificates-1a-doc2`, based on `main`. The implementation and review
corrections are still in the working tree; this is not a release declaration.
See [doc-2-people-and-enrollment.md](doc-2-people-and-enrollment.md) for the current behavior,
authorization boundaries, tests, and open issues.

- External `certification_candidates` are separate from staff users. Initial legacy backfill preserved
  every row; the forward `2026_10_25_131838` correction reconciles matching legacy names into shared people.
  Locally 26 people became 10, preserving 26 roster links and the existing enrollment. No progress,
  staff accounts, or emails are invented. Conflicting data aborts reconciliation for review.
- Agreement roster links preserve metadata and have a unique agreement/person pair. Global
  `certificate_enrollments` have a unique person/certificate pair and optional sponsor/coach.
  Agreement removal/duplication does not clone or delete people/enrollments or reset sponsorship.
- Create/edit supports candidate selection, new people, optional coaches, certificate assignment,
  and explicit authorized correction/removal of initial NotStarted cycle-1 enrollments.
  Untouched legacy people can retain null email; actual identity edits collect email.
- Edit and show both filter enrollments by viewer visibility and agreement programs. Shared identity
  and global enrollment mutations check the affected agreements. A missing sponsor grants no authority.
  Existing retired selections remain preservable; new assignment eligibility remains strict.
- Coach selection requires a matching certificate-program duty and the coach's agreement visibility.
  Program changes update the form options. Duties do not confer access.
- Duty controls exist on authorized admin Edit User forms. Selected program duties are visible and
  removable; omission preserves existing duties. Authorized admins can self-grant/revoke through the
  duty-only Profile form and `profile.certification-duties.update` route. Scope validation and the
  existing duty authority apply; general user self-edit permission remains unchanged.
- Candidate search shows names/emails only. Known emails explicitly reuse a person or open their
  existing roster row. Shared identity remains locked until explicitly edited with authority. Adding
  a candidate starts with search-or-create; selecting an existing person immediately shows their
  applicable global assignments. Certificate rows are flat within one focused editor.
- The main-column Certificate assignments section shows certificate/coach/date and links directly to
  the matching editor. Assigned is an assignment state; computed completion and automatic credit are
  still Doc 3, not inferred from the initial status.
- Review verification: `composer check` passed (252 tests / 978 assertions, PHPStan, Pint, audit);
  63 focused certification/profile cases and the frontend build passed. An isolated PostgreSQL-schema
  backfill test was added. Two independent Impeccable reviews and Playwright checks informed the UI fixes.
  No live application form was saved during this review.
- **1A is now complete in the same working tree**; see its separate handoff below.
  Doc 3 progress/evidence and Doc 4 endorsement/award/renewal remain future work, including their
  lifecycle columns. No activity-based completion or progress calculation exists yet.


### Follow-up 1A — Catalog Authoring (COMPLETE; UNCOMMITTED)

Branch: `feature/certificates-1a-doc2`. This completes the bounded catalog-authoring follow-up; it is not
committed or released. Doc 1's shipped schema stays unchanged. See the completed follow-up in
[doc-1-catalog-foundation.md](doc-1-catalog-foundation.md) for the interaction and preservation contract.

- Certificate authoring now uses freely navigable Basics, Initial requirements, Renewal and Review rules.
  Compact summaries open one focused inline editor. Activity work counts completion units; tool
  submissions have a total plus independently evaluated coverage/quota rules; confirmation remains manual.
- Groups physically contain their requirements. Contextual role editing supports new-role assignment in
  one save through `role_index`; stage-scoped `group_index`, stable IDs, ordering and deletion survive.
  Role/program filters, effective windows, guidance and linked renewal appear in the full rule review.
- Renewal can follow initial configuration or copy it into independent records. Destructive replacements
  and incompatible type/tool changes confirm. Out-of-scope selections are preserved for correction.
  Nested ownership/category/stage validation prevents cross-record edits and incompatible hidden rules.
- Confirmed guide semantics above replace the old open Full/Phase clarification. No schema change,
  numerical score comparison, prerequisite sequencing, activity evidence or progress implementation was
  added. Doc 3 must use the same eligible/passing pool for totals, phase coverage and observation quotas.
- Doc 2 remains the completed assignment flow, including the legacy-person reconciliation and assignment
  section on agreements. Its completion does not imply any candidate has earned progress. Doc 4 retains
  formal endorsement, awarding, expiration and renewal execution.

- Final combined check: `vendor/bin/sail composer check` passed (266 tests / 1,062 assertions, audit,
  Pint and PHPStan); the production frontend build passed. Two independent UI assessments and the
  focused browser recheck informed the final empty-state, contrast and validation-focus fixes. The
  certificate-partials detector returned zero findings. Runtime checks had no console errors or
  horizontal overflow at desktop/390px. No valid catalog forms were saved to the development database.
- Own-diff cleanup replaced the old inline orchestration, duplicate lookback controls and unused
  preparation code with a certificate-local editor. No generalized builder or new dependency was added.
