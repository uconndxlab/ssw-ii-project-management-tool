# Access rules: certification review and supervisors

Rules added with the coach review request feature. The wider activity rules live in
`app/Policies/ActivityPolicy.php` and `app/Support/Authorization/UserAccess.php`.

## Supervising coach on an activity

A user is a **certification reviewer** on an activity when either is true:

- they are the `supervising_coach_user_id` on any enrollment of a candidate tagged on the activity, or
- a row in `activity_review_requests` names them (an editor asked them to review).

A reviewer can **view** the activity and **edit only its certification evidence** through
`activities.certification.edit` / `activities.certification.update`. The rest of the activity stays
editable only by people who already have edit access (`ActivityPolicy::update`). Ability:
`ActivityPolicy::reviewCertification`.

Review request rows exist only while the activity's review flag is on. Clearing the flag deletes them,
so asked-reviewer access ends with the review. Supervising-coach access follows the tags and does not.

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
