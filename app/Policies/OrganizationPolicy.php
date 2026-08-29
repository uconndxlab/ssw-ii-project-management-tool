<?php

namespace App\Policies;

use App\Models\Organization;
use App\Policies\Concerns\AuthorizesScopedEntity;

class OrganizationPolicy
{
    use AuthorizesScopedEntity;

    // View: you are on organization_user, or a program on the org is in your view/admin privilege.
    // Team membership alone does not grant org view.
    // Edit: you admin a program assigned on the org.
    // Delete: every program on the org is in your admin scope. No programs: system admin only.
    // Create: any admin.
}
