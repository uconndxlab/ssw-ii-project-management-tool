# Access rules: certification review and supervisors

Rules added with the coach review request feature. The wider activity rules live in
`app/Policies/ActivityPolicy.php` and `app/Support/Authorization/UserAccess.php`.

## Supervising coach on an activity

A non-Input user is a **certification reviewer** on an activity when either is true:

- they are the `supervising_coach_user_id` on any enrollment of a candidate tagged on the activity, or
- a row in `activity_review_requests` names them (an editor asked them to review).

A reviewer can **view** the activity and **edit only its certification evidence** through
`activities.certification.edit` / `activities.certification.update`. The rest of the activity stays
editable only by people who already have edit access (`ActivityPolicy::update`). Ability:
`ActivityPolicy::reviewCertification`.

Clearing the activity's review flag marks its request rows cleared (`cleared_at`) instead of deleting
them, so a coach who was asked keeps access to the evidence they reviewed. Asking the same coach again
reopens the row without a second email: only the first request emails. Removing a coach's chip before the
review deletes the row. Supervising-coach access follows the tags and never expires.

Coaches (a reviewer on the activity, or a coach duty in one of its programs) get an "Edit certification"
action in the activities list and an "Edit certification evidence" button on the activity page. Both open the
focused certification page. Other editors see only the whole-activity edit. A coach who may also edit the
whole activity sees both.

## Supervisors act for their direct reports, per candidate

Rule: a supervisor can do what they can do as a user **and**, for a candidate whose assigned supervising
coach is one of their direct reports, what that coach can do. Direct reports only
(`UserAccess::directReportIds()`); there is no chain.

The inheritance is scoped to the enrollment, never to the report's duty as a whole. A supervisor of a
coach gets no coaching standing in that coach's programs, and no manager duty.

- Activities: a supervisor is a reviewer on any activity tagging a candidate their direct report supervises,
  or one their report was asked to review.
- Recording submissions, confirming, and the progress page: `CertificationAccess::coachProgramIdsViaSupervisee()`
  returns the report's coach programs only for an enrollment whose `supervising_coach_user_id` is a direct
  report. `CertificateProgressAccess` (`canViewDetails`, `canConfirm`) and
  `CertificationEvidenceService::canRecord` use it.
- Endorsement: `CertificateProgressAccess::canEndorse` accepts the enrollment's supervising coach **or
  their supervisor**. This gives a supervisor with no coach duty of their own the power to endorse that
  candidate. That is what the client asked for.
- Not inherited: manager duty (`canManage`), and any candidate the report does not supervise. A user's own
  `coachProgramIds()` and `managerProgramIds()` are unchanged, so a supervisor is never assignable as a coach
  through this rule.

## Candidates, certificates and tools (best guess, pending client)

Added with the candidates index and the certificate show page. These are our best guesses while the
client answers. Trello card "Update candidate and certificate access rules based on client answers" tracks the
open questions. Change the rules there and here together.

- **Seeing a candidate** (`UserAccess::applyCertificationCandidateVisibility`, `CertificationCandidate::visibleTo`):
  you can see an agreement they are on, or you or a direct report supervise one of their enrollments.
  System admins see everyone, including people on no agreement. Input users see none.
- **Managers:** the duty adds nothing. A manager sees candidates through view privileges or membership, the
  same as today. Open question for the client: should manager duty show enrollees of certificates in the
  manager's programs even without agreement access?
- **Editing a candidate** (name, email, phone, start date, notes, active): a system admin, or someone who can
  edit every agreement the person is on (`CertificationAssignmentSyncService::canEditCandidate`). Same rule as
  the agreement form.
- **Seeing a certificate** (index and show page):
  - admins in scope can see and edit it, as before;
  - enhanced viewers (view privileges only) see it in the same scope, read-only;
  - coaches and managers see certificates in their duty programs, read-only. An "All programs" certificate
    counts for any duty; a "No programs" certificate counts for none.
  - `Certificate::visibleTo()` retains its original behavior: non-Input users with no applicable
    privilege constraint get an unconstrained certificate scope. Scoped enhanced viewers see only
    privilege-overlapping certificates through this scope; duties do not extend it. The certificate
    index separately includes duty-authorized certificates, and the show policy checks privileges or duties.
- **Enrollees on a certificate page** still follow the candidate rule. A coach can see a certificate with
  nobody listed.
- **Certification tools:** admins as before. Enhanced viewers see the index only, read-only. Coaches and
  managers do not.
- **Hidden records:** where a list hides records you cannot see, it shows "+N others" (a count, never
  names). This applies to certificate enrollees, a candidate's enrollments and agreements, and the
  candidates index rows.
- **Navigation:** coaches and managers without admin or view privileges get Candidates and Certificates in
  the Work group. Admins and enhanced viewers get Candidates, Certification Tools and Certificates under
  Certification Setup, whether or not they hold a duty.

## How link checks are batched

`app/Models/Concerns/VisibleToUser.php` records ids as Eloquent retrieves models, including eager-loaded relations.
`app/Support/Authorization/HydratedRecordIds.php` stores only those ids, grouped by model class.
Its scoped binding in `app/Providers/AppServiceProvider.php` resets each request, job, or Octane tick.
On a cache miss, `app/Support/Authorization/UserAccess.php` checks all unanswered ids of that type, including the requested id.
It uses the same list visibility filter, in chunks of 1000; existing PHP gates and policy exceptions still apply.
Activity visibility, certification reviewers, and action-log scope have separate batches; user membership keeps its supervisor exceptions.
Both allowed and denied answers are cached per `UserAccess` instance for the request; different users never share answers.
Models retrieved later trigger another batch. Debug with the query log: look for one `IN (...)` per type, predicate, and chunk.

## Action logs require activity access

An activity's action log is available only if you can view that activity. The existing
logger, logger's supervisor, and view/admin action-log checks still apply after that check.
Program membership alone cannot expose another person's activity history.

## Setup lists and activity logging

Contact-family, logging-field, activity-type, sub-activity and certification-tool setup lists
require view/admin privilege over an assigned program. With no applicable
privilege or membership rule, the list is empty. Program/team membership does not grant
setup-list access. Certificates retain their original visibility, including the empty-group
behavior described above. Their index separately includes certificates allowed by your own
active coach/manager duties (including All programs certificates, but never No programs).

Activity logging keeps its existing families, types, sub-activities, logging fields,
certificate choices and tools. Those form choices do not grant access to setup pages.
Agreement staff keep certificate names, enrollment summaries, award dates, certificate filters
and certificate progress pages for candidates on their own agreements. Coach/manager duties
do not narrow that visibility. Progress details and lifecycle actions retain their existing
privilege/duty checks. Input users still see no certificates or progress pages.

## Input users cannot coach or manage

Input users cannot receive coach/manager duties, be selected as supervising coaches, or
be asked to review an activity. Pickers exclude them and submitted IDs are validated.
Revoke active coach/manager duties before changing a user's profile to Input; the change
is rejected rather than silently revoking duties. Revoked duties do not block the change.
Existing duty, enrollment or review-request rows give an Input user no coach/manager or
reviewer standing. Input users retain their normal logger and Delivered By activity access.

## Inactive agreements require privilege overlap

An inactive agreement is listed in the agreement index and opens only with system view/admin access or view/admin privilege
over one of its assigned programs, including programs covered by a project privilege.
All programs on an agreement does not itself create overlap; assigned program pivots count,
as they do on agreement lists. Staff, team membership and supervision alone cannot open it,
even with a privilege elsewhere. Normal members never see inactive agreements in the index;
enhanced viewers and admins see them only within their privilege scope, including when they
are staff on an out-of-scope agreement. The `active` filter operates within this permitted set.
Active-agreement visibility is unchanged. Candidates and states reached through inactive
agreements keep their existing visibility through the unchanged base agreement scope.
The index constraint and show/link checks reuse the same assigned-program privilege overlap
helper. Link checks batch privilege overlap per request rather than querying once per agreement.


## Why can I see this? (My role tags)

`app/Support/Authorization/AccessReasons.php` projects the shared
`UserAccess::activityVisibilityPredicates()` and `agreementVisibilityPredicates()`
for the whole table page in one query. List visibility ORs those same predicates;
reason labels split personal references from direct-report references and name each
matching program/project privilege. Admin replaces Viewer for the same scope.
System access uses `hasSystemView()` and `isSystemAdmin()`. Input users gain no
reviewer standing; membership tags do not explain inactive agreements, whose access
still requires privilege overlap. The column explains visibility and grants no access.

## Manual award history

System admins can record a candidate’s past award history. Other admins must administer every
program on every agreement the candidate is rostered on, and the sponsoring agreement, and be
able to update the certificate (All-program certificates are exempt from that catalog check).
Project admins inherit their projects’ programs. All-program and no-program agreements require
a system admin. Input users, coach duty and manager duty do not grant this permission.

History can be recorded only before any award or endorsement exists, and edited only while every
award is manual and no in-app endorsement exists. Revoked enrollments are closed. Saved awards
cannot be removed or reordered; new awards append at the end. Non-expiring certificates allow
one award. Saving expiring history opens renewal cycle N+1 after N awards; only activity after the
last award date counts. Existing confirmations stay with their original cycles. Missing prerequisites
warn but do not block this migration tool. Every save records the admin, reason and changed values.
