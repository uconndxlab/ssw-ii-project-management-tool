---
applyTo: "database/factories/**,database/seeders/**"
---

Read the live Postgres schema for nullability and defaults rather than inferring
from migrations (91 layered migrations with renames and drops).

String columns without an enum cast take values from AgreementRequest validation
rules and model constants — never from Faker's generic word providers.

Interdependent column groups (e.g. AgreementDeliverable metric_type,
time_basis, contribution_basis, user_grouping_mode) get named factory states,
not independent randomization.

belongsToMany relationships never appear in factory definition(); attach pivots
only in explicit named states via afterCreating.

Self-referential foreign keys (e.g. users.supervisor_id) default to null;
expose a withSupervisor() state instead.

Reference data: sail artisan migrate:fresh --seed (StateSeeder, LoggingFieldSeeder).
Demo examples: sail artisan db:seed --class=DemoSeeder after reference seeders.

Do not combine StateFactory with StateSeeder in the same test — both name and
code are unique and Faker exhausts before StateSeeder's 59 rows.

No factories for projection tables written only by services
(AgreementActivityHistory, DeliverableContribution, ActivityAgreementFundingSource).
