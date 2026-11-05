<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;

/**
 * Report access is its own layer: it deliberately does not follow agreement or activity access,
 * because the client will define separate rules for who may run reports.
 */
class ReportRequestPolicy
{
    /**
     * Run a report on an agreement and see its report history.
     */
    public function create(User $user, Agreement $agreement): bool
    {
        // shortcut: system admins only until the client defines report access rules.
        return $user->isSystemAdmin();
    }
}
