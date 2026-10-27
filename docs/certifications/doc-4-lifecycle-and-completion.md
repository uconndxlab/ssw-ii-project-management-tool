# Doc 4 — Certification Lifecycle & Completion

Status: **implemented in the working tree on `feature/certificates-doc4`, unstaged and uncommitted.**
Branch created from `feature/certificates-doc3` @ `3ec5e7b`. Drafts and diagrams that informed the
approved direction: `.artifacts/doc4/{ux-drafts,diagrams}.html` (gitignored).

## Decisions (approved 2026-10-26/27)

- **Gate:** requirements met (live) → the enrollment's **supervising coach** endorses → a certification
  manager awards. The NWIC guide (Spring 2026) does not describe an approval workflow. It only says
  adaptations "must be approved by NWIC" and that Trainer certification is never standalone. The
  two-person gate comes from spine decision 9 (client-stated), not from the guide.
- **Endorsement freezes the record.** Endorsing stores the requirement snapshot. The award confirms
  that frozen record and does not re-evaluate live progress, so later evidence/catalog changes or
  rolling windows never block or alter it. A manager can **Return to coach** (reason required). The
  endorsing or supervising coach can **Withdraw** (reason optional).
- **Separation:** the manager who awards must not be the endorser of that cycle.
- **Duties, not admin access:** endorse requires being the supervising coach with coach duty in the
  certificate's programs. Award, return, revoke and coach change require manager duty in scope. Both
  also require enrollment visibility. The checks live in `CertificateProgressAccess`, not policies, so
  the system-admin `Gate::before` bypass never applies. `All`-scope certificates keep the existing
  meaning (duty in any program).
- **Prerequisites:** at every award (initial and renewal) the candidate must hold a live award
  (issued, unrevoked, unexpired) for all/any prerequisite certificates. A later lapse never revokes.
- **Expiry:** `expires_at` is a date; a credential is valid while today is before it. Expired is
  computed (no job, no status write). On-time renewal extends the previous expiry by
  `validity_months`; a first or late award runs from its award date. Late renewal stays on the
  renewal requirements. `validity_months = null` never expires and never opens renewal.
- **Renewal:** awarding an expiring certificate opens cycle N+1 (`phase = renewal`) automatically.
  Its evidence boundary is that award's date, supplied to `CertificateProgressService::evaluate()`.
- **Revocation:** manual, reason required, logged. Only the **current** award (latest issued, live)
  can be revoked. Expired and superseded awards are history. Revocation closes the enrollment: no
  endorsement or award afterward. Evidence and history remain.
- **Visibility:** ordinary viewers see status, dates, requirement counts and names (coach, endorser,
  awarder). Revocation reasons, action history, award records/snapshots and evidence require
  `canViewDetails`.
- **Snapshot contents:** certificate, cycle/phase/linked flag, previous award date, evaluation time,
  met/total, groups, every requirement (configuration, count/target, rules, attestation who/when/note)
  and counted evidence including dimension options, **score values and notes**. Prerequisite awards
  used are added at award time.
- **Retired/inactive certificates:** existing enrollments can still be endorsed, awarded and renewed.

## Schema (one forward-only migration, `2026_10_27_132318`)

- `certificate_awards`: one row per (enrollment, cycle), `unique(certificate_enrollment_id, cycle)`.
  Created at endorsement with `endorsed_at/by`, `endorsement_note` and `requirements_snapshot`;
  `awarded_at` null means "endorsed, awaiting award". The manager sets `awarded_at/by`, `award_note`
  and `expires_at`. Revocation sets `revoked_at/by` and `revoke_reason`. A withdrawn/returned
  endorsement deletes the pending row; the action log keeps the history.
- `certificate_action_logs`: enrollment, nullable award, nullable user (null = system), action, cycle,
  reason, context json, `created_at` only.
- No enrollment columns were added. `status` is written only by lifecycle actions (`endorsed`,
  `in_progress`, `awarded`, `revoked`); requirements met and expired are always computed.
  `pdf_*` remains Doc 5.

## Implementation

- `App\Services\CertificateLifecycleService`: `previousAwardDate`, `evaluate`, `pending`,
  `prerequisites`, `endorse`, `withdraw`, `award`, `revoke`, `changeCoach`, `eligibleCoaches`,
  `expiresAt`. Each mutation runs in a transaction, locks the enrollment, compares the posted cycle and
  endorsement id, writes, and logs. Mismatches raise `App\Exceptions\CertificateLifecycleConflict`,
  which the controller turns into an error flash that says nothing was saved. Duplicate awards report
  "Already awarded for cycle N by …".
- `CertificateLifecycleController` routes: `POST/DELETE certificate-enrollments/{e}/endorsement`,
  `POST …/awards`, `PUT …/supervising-coach`, and with scoped bindings `GET …/awards/{award}`
  and `POST …/awards/{award}/revocation`. All carry `agreement_id` for breadcrumbs.
- Coach change reuses Doc 2's `eligibleCoaches()` and also requires the coach's own enrollment
  visibility. A pending endorsement survives a coach change. Doc 2's agreement-form correction remains
  limited to unstarted enrollments.
- `CertificateEnrollment::lifecycleBadge()` is the single status shown on the progress header and the
  agreement assignment row: Revoked → Certified · until/no expiry → Expired → Endorsed → Assigned →
  In progress.
- Views: `certification/partials/lifecycle-status` (credential, three-step track, next step, inline
  `<details>` action panels), `partials/awards-history`, `partials/revoke-form` and `certification/award`
  (read-only snapshot). Breadcrumbs: Agreements → visible agreement → candidate → cycle award.

## Verification

- `tests/Feature/CertificateLifecycleTest.php` (17 cases): the full gate and renewal boundary,
  on-time/late expiry, non-expiring certificates, duty matrix including a system admin without duties,
  self-award refusal, endorsement freeze, prerequisites (none/expired/revoked/live), stale and
  duplicate submissions, return vs withdraw reasons, revocation (reason, closed lifecycle, ordinary
  viewer privacy, superseded and expired refusal), coach change eligibility and the snapshot page.
- `vendor/bin/sail composer check` passed (312 tests, audit, Pint, PHPStan); `npm run build` passed.
- Playwright (1440px and 390px): coach endorsement, manager award with automatic renewal, award record,
  agreement badge, Trainer prerequisites; no console errors or warnings, no horizontal overflow.
  Impeccable detector: zero findings. Screenshots: `.artifacts/doc4/0*.png`.

## Local demo (development only)

Clearly labeled records, created without touching existing people, enrollments, evidence or option IDs:
candidate `Doc 4 Demo — Jordan Reyes` on agreement 8; certificate `Doc 4 Demo — Lifecycle check`
(NWIC program, one initial and one renewal coach confirmation, 12-month validity; enrollment 4, now
awarded with renewal cycle 2 open); NWIC Trainer enrollment 5 showing missing prerequisites; user
`doc4-manager@example.test` / `password` with program-3 admin and manager duty; Sarah Johnson gained
NWIC (program 3) coach duty. Revoke any duty row through Edit User if it is no longer wanted.

## Deferred

PDFs, notifications and expiry reminders, central indexes and a candidate directory, reinstatement
after revocation, and automatic COMET inter-rater sequencing.
