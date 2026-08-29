<?php

namespace App\Policies;

use App\Models\Team;
use App\Policies\Concerns\AuthorizesScopedEntity;

class TeamPolicy
{
    use AuthorizesScopedEntity;

    // View: you are on the team, or a program on the team is in your view/admin privilege.
    // Edit: you admin a program assigned on the team.
    // Delete: every program on the team is in your admin scope. No programs: system admin only.
    // Create: any admin.
}
