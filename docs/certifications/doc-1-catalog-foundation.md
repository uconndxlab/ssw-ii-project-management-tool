# Doc 1 — Catalog Foundation

**Status: SHIPPED.** See the spine's Handoffs section for what actually landed and any deviations.
The catalog authoring follow-up below is **PLANNED**, not part of the shipped completion claim.

Prereq: read `docs/certifications/spine.md` first.
Goal: an admin can build scoring tools and author a full certificate (initial + renewal requirement sets).
No candidates, no enrollment, no progress. Those are Docs 2 and 3.

## Scope
IN : enums; duties + CertificationAccess; tool builder CRUD; certification roles; certificate CRUD with
     requirement groups, requirements, dimension rules, prerequisites; policies; routes; nav.
OUT: candidates, enrollment, tagging, progress, awards, PDFs, notifications.

## Phase A — Enums
`app/Enums/`
- CertificateRequirementKind      : activity_count | tool_submission | attestation   (+ label())
- CertificateRequirementPhase     : initial | renewal                                 (+ label())
- CertificateDimensionRuleMode    : coverage | quota                                  (+ label(), description())
- CertificateGroupSatisfyMode     : all | any | n_of                                  (+ label())
- CertificationToolScoreUnit      : percent | points | number                          (+ label(), suffix())
- CertificationDuty               : coach | manager                                    (+ label())
- CertificateEnrollmentStatus     : not_started|in_progress|requirements_met|endorsed|awarded|expired|revoked
                                    (+ label(), icon(), tone()) — modeled on DeliverableStatus. Defined now,
                                    used in Doc 4.
- CertificateTagOutcome           : passed | not_passed | not_scored  (+ label(), tone()) — used in Doc 3.
`PrivilegeCapability` MUST NOT be touched.

## Phase B — Migrations
One migration per logical group, dated `2026_09_15_*`. Follow existing style: `foreignId(...)->constrained()
->cascadeOnDelete()` / `->nullOnDelete()`, `$table->timestamps()`, explicit unique + index names where long.

1. create_user_certification_duties_table
   user_id FK cascade, duty string, scope_type string, scope_id unsignedBigInteger nullable,
   granted_by_user_id FK users nullOnDelete, revoked_at timestamp nullable,
   revoked_by_user_id FK users nullOnDelete, timestamps.
   index (user_id, duty), index (duty, scope_type, scope_id).
   NO unique — revoked rows are retained as the audit trail.
2. create_certification_tools_tables
   certification_tools: name unique, slug unique, description text nullable, active bool default true,
     sort_order unsignedInteger default 0, program_scope_mode string, retired_at timestamp nullable, timestamps
   certification_tool_program: tool FK cascade, program FK cascade, timestamps, unique(tool, program)
   certification_tool_dimensions: tool FK cascade, name, slug, sort_order, timestamps,
     unique(certification_tool_id, slug)
   certification_tool_dimension_options: dimension FK cascade, label, value, sort_order, timestamps,
     unique(certification_tool_dimension_id, value)
   certification_tool_score_fields: tool FK cascade, name, slug, unit string, sort_order, timestamps,
     unique(certification_tool_id, slug)
3. create_certification_roles_table
   name, slug, certificate_id FK nullable cascade (null = standard/global), active bool default true,
   sort_order, timestamps. unique(certificate_id, slug) — global rows share certificate_id NULL, so also add
   a partial guard in validation rather than relying on the unique for NULLs.
4. create_certificates_tables
   certificates: name unique, description text nullable, active bool default true, sort_order,
     program_scope_mode string, validity_months unsignedInteger nullable,
     default_window_months unsignedInteger nullable, prerequisite_mode string default 'all',
     retired_at timestamp nullable, timestamps
   certificate_program: certificate FK cascade, program FK cascade, timestamps, unique
   certificate_prerequisites: certificate_id FK cascade, required_certificate_id FK cascade, timestamps,
     unique(certificate_id, required_certificate_id)
5. create_certificate_requirements_tables
   certificate_requirement_groups: certificate FK cascade, phase string, label, satisfy_mode string,
     required_count unsignedInteger nullable, sort_order, timestamps
   certificate_requirements: certificate FK cascade,
     certificate_requirement_group_id FK nullable nullOnDelete, phase string, kind string,
     contact_family_id FK nullable nullOnDelete, activity_type_id FK nullable nullOnDelete,
     certification_tool_id FK nullable nullOnDelete, certification_role_id FK nullable nullOnDelete,
     target_count unsignedInteger default 1, requires_passing bool default true,
     threshold_note string nullable, window_months unsignedInteger nullable,
     label nullable, notes text nullable, sort_order, timestamps
     index (certificate_id, phase), index (kind)
   certificate_requirement_dimension_rules: requirement FK cascade, dimension FK cascade,
     mode string, option_ids json, min_count unsignedInteger nullable, timestamps

Note: `certificate_requirements.requires_passing` defaults TRUE but is only meaningful for
kind = tool_submission. Leave it untouched for other kinds.

## Phase C — Models
`app/Models/`: CertificationTool, CertificationToolDimension, CertificationToolDimensionOption,
CertificationToolScoreField, CertificationRole, Certificate, CertificateRequirementGroup,
CertificateRequirement, CertificateRequirementDimensionRule, UserCertificationDuty.
- CertificationTool + Certificate: `use HasProgramScope, VisibleToUser;` + `programs()` BelongsToMany +
  `scopeActive()` + `scopeNotRetired()`. Cast program_scope_mode to ProgramScopeMode.
- ADD both classes to the match statement in `app/Models/Concerns/VisibleToUser.php` ->
  `applyScopedEntityVisibility($query)`. Easy to miss; without it `visibleTo()` throws.
- Ordered relations: dimensions/options/scoreFields/groups/requirements all `orderBy('sort_order')`.
- Certificate::requirements() HasMany; add `requirementsForPhase(CertificateRequirementPhase $phase)`.
- Certificate::prerequisites() BelongsToMany self via certificate_prerequisites
  (foreign `certificate_id`, related `required_certificate_id`).
- User: add `certificationDuties()` HasMany + `certification()` returning CertificationAccess::for($this).

## Phase D — CertificationAccess
`app/Support/Authorization/CertificationAccess.php`, mirroring UserAccess's static `for()` + per-user cache.
- isCoach(): bool / isManager(): bool                       (any active, non-revoked duty of that type)
- hasDuty(CertificationDuty $duty): bool
- coachesProgram(int $programId): bool / managesProgram(int $programId): bool
- coachProgramIds(): array / managerProgramIds(): array
- canGrantDuty(User $target, CertificationDuty, PrivilegeScopeType, ?int $scopeId): bool
  -> delegates to UserAccess: system admin grants anything; project/program admin grants within own scope.
     SELF-GRANT ALLOWED.
- System-scope duty implies all programs. Project-scope duty implies that project's programs.
- Only rows with revoked_at IS NULL count.
MUST NOT modify UserAccess, AccessProfile, or PrivilegeCapability.

## Phase E — Policies
`CertificationToolPolicy`, `CertificatePolicy`, `CertificationRolePolicy` — all
`use AuthorizesScopedEntity;` and override viewAny/view exactly like ContactFamilyPolicy
(`hasAdmin()` and `hasAdmin() && canViewRecord()`).
Auto-discovered; no registration. Guard destroy(): a tool in use by any requirement, or a certificate with
any requirement/enrollment, retires (`retired_at`) instead of deleting.

## Phase F — Controllers
`CertificationToolController`, `CertificateController`. Model ContactFamilyController closely:
HTMX partial index (`$request->header('HX-Request')`), search/active/project/program filters, sortable
columns, 20/page; `ProjectProgramScope::assignableProjectsWithProgramsFor(Auth::user())` on create/edit;
`Validator::make` + `->after()` with `ProjectProgramScope::validateModeSelection()`;
`ScopeSync::applyTo()` for program scope; nested repeater sync inside `DB::transaction`.

Nested sync rules (both controllers):
- Rows arrive keyed by `row_key` with `id` and `_delete`, same shape as the deliverables section.
- Rows with no meaningful content are skipped, not created.
- `_delete=1` + existing id -> delete (or retire when in use).
- `sort_order` assigned from submitted order.
- Certificate validation: kind=tool_submission REQUIRES certification_tool_id; dimension rules must belong
  to that tool; mode=quota REQUIRES min_count; mode=coverage REQUIRES >=1 option_id;
  group satisfy_mode=n_of REQUIRES required_count <= number of requirements in the group;
  a certificate cannot be its own prerequisite, and prerequisite cycles must be rejected.

## Phase G — Routes + nav
`routes/web.php`, inside the existing `['auth','active']` group, beside contact-families/activity-types:
  Route::resource('certification-tools', CertificationToolController::class)->except(['show']);
  Route::resource('certificates', CertificateController::class)->except(['show']);
Add both to `app/Support/AppNav.php` under the existing admin section.

## Phase H — Views
`resources/views/admin/certification-tools/` and `resources/views/admin/certificates/`, each with
`{index,create,edit}.blade.php` + `partials/{filters,table,form-fields}.blade.php`.
- Tool form: name, description, active, `x-project-program-scope-picker`, then two repeaters —
  Dimensions (each with a nested options repeater) and Score Fields (name + unit).
- Certificate form: name, description, active, scope picker, expiry (validity_months, empty = never),
  default_window_months, prerequisites (`x-token-picker` over other certificates) + prerequisite_mode,
  then Initial / Renewal tabs. Each tab lists groups; each group lists requirements; each requirement of
  kind=tool_submission exposes dimension rules for the chosen tool.
- Follow the deliverables-section pattern: visible summary table + hidden inputs + `x-form-drawer` editor.
  Build a new `x-repeater-rows` multi-field component (generalizing `x-participant-time-rows`) rather than
  copy-pasting its JS four times.

## Acceptance
1. `./vendor/bin/sail artisan migrate` applies cleanly; `./vendor/bin/sail artisan test` green.
   NEVER migrate:fresh / rollback — forward-only. See the spine's command rules.
2. Author tools: COMET (dimensions Phase 1-4, Review Mode Document|Full; score fields Overall Match %,
   Element Match %), Supervisor Checklist (same shape, Element 80%), SAS (no dimensions; score fields Total,
   Coaching, Communication, Analysis), CREST (no dimensions, no score fields).
3. Author the NWIC Local Coach certificate per the spine's acceptance table, including the renewal set at
   reduced targets. Every row must be expressible.
4. Author the Trainer certificate with prerequisite_mode=any over Coach + Supervisor.
5. A program-scoped admin sees only their own programs in both pickers; a non-admin gets 403 on create.
6. Grant a user the coach duty at program scope; grant yourself the manager duty; confirm the revoked row
   is retained.
7. Deleting a tool referenced by a requirement is refused (retire offered instead).

## Result
Fully implemented and verified (via direct controller-level smoke tests in tinker, since no browser tool
is available in this environment — all test data was created and cleaned up in the same pass). See the
spine's Handoffs section for exact names, deviations, the `x-repeater-rows` component API, and notes for
Doc 2.

## Follow-up 1A — Catalog authoring usability (PLANNED)

Goal: an admin can express and review certificate rules without understanding the nested storage model.
This is certificate work for the separate certificate release, using the existing visual system on
`main`. The exploratory mock established a direction, not an approved specification for every rule.
Keep the shipped foundation and Docs 2-5 numbering intact; append a spine handoff only when this follow-up
actually ships. Doc 2 can proceed independently. Settle the authoring pattern before Doc 3's related UI.

### Clarify the rule before designing its controls
- The user flagged the mock's Full-review/Phase interaction as inaccurate. Obtain concrete examples of
  valid submissions and expected counts, including coverage, minimum counts and how Full relates to Phase.
  Correct the spine's affected acceptance/evaluation details before implementing that interaction or the
  Doc 3 evaluator. If the confirmed rule needs a model change, scope it explicitly; do not silently encode
  different semantics through new labels or controls.
- Distinguish certificate stages (Initial/Renewal) from tool dimensions such as Phase. Scores and threshold
  notes remain recorded guidance; coaches judge passing outcomes. Never introduce automatic score math.

### Authoring direction
- Organize by task: Basics, Initial requirements, Renewal, Review rules. Allow direct navigation between
  sections rather than requiring a Next/Back wizard. Preserve the existing form shell and Save/Cancel
  behavior; navigation and a local editor must not silently save part of the certificate.
- Show a compact requirement list with meaningful summaries and one focused editor at a time. Prefer an
  inline editor beneath the selected item initially; verify complex examples before choosing a drawer.
  Start a new requirement by choosing Activity work, Tool submissions or Coach confirmation, then reveal
  only relevant fields. Preserve completion-unit counting rather than implying every activity counts once.
- Present grouped requirements together under their all/any/set-number rule. Offer grouping where authors
  work with the requirements, instead of making them create groups elsewhere and remember dropdown links.
  Ungrouped requirements remain individually required; do not invent arbitrary nested Boolean groups.
- Keep certificate-specific role management contextual to the role selector. Keep any-role filtering and
  existing role data available. Global-role management and duty administration retain their separate scope.
- Explain certificate defaults and per-requirement overrides explicitly. Show the effective counting window
  and program/role filters in summaries. Distinguish award validity from how far back work can count.
- Make following initial rules versus copying them explicit: following remains linked; copying starts an
  independent renewal list. Preserve support for genuinely different renewal requirements and confirm
  destructive replacement/clearing using mechanisms available on the certificate branch.
- Provide readable rule summaries for authors to check before saving. Summaries must reflect all effective
  conditions, including groups, overrides and inherited renewal rules. This is configuration review, not
  candidate progress evaluation; progress, endorsement and awarding remain Docs 3-4.

### Boundaries and preservation
- Reuse the Blade/Bootstrap components available on `main`; no dependency on enterprise-restyle tokens,
  layouts or new shared JS. Preserve program scope, prerequisites, permissions and existing save semantics.
- No new draft/publish workflow, autosave, candidate evaluator or general-purpose rules engine. Do not
  redesign deliverables or the scoring-tool builder as a prerequisite for this authoring follow-up.
- Preserve stable IDs, phase/group membership, ordering, deletion markers and new unsaved group references
  through editing and validation errors. Existing catalog records must reopen without losing data. Treat
  a field hidden for a different requirement kind deliberately; do not silently retain contradictory rules
  or discard authored data when switching types/tools.

### Acceptance and handoff
1. Confirm the Full/Phase examples and update the spine first. Author and reopen the full NWIC Local Coach
   example with its confirmed rules, plus Trainer prerequisites/roles and a simple coach attestation.
2. Verify grouped and ungrouped requirements, initial/renewal edits in the same submission, linked renewal,
   independent copies, ordering and confirmed removals. Validation failures preserve values and open the
   relevant section/editor with errors linked to controls.
3. Check keyboard-only operation, named controls, focus after add/edit/remove and navigation between
   sections. Reordering must have a keyboard alternative. Inspect desktop and phone layouts with realistic
   long labels and complex rules; check browser errors and existing authorization boundaries.
4. Add meaningful regression coverage for changed behavior, then run the repository's required checks.
   Use factories/tests for test data; do not seed over or recreate the development database.
5. Record the proven authoring conventions in the existing form-conventions document and append the spine
   handoff. Later, evaluate the pattern against one difficult deliverable before extracting shared UI.
   After certificate work reaches `main`, integrate it into the design branch and reconcile styles there.
