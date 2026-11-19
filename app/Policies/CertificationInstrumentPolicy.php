<?php

namespace App\Policies;

use App\Models\CertificationInstrument;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class CertificationInstrumentPolicy
{
    use AuthorizesScopedEntity;

    public function viewAny(User $user): bool
    {
        return $user->access()->hasView();
    }

    public function view(User $user, CertificationInstrument $certificationInstrument): bool
    {
        return $user->access()->hasView() && $user->access()->canViewRecord($certificationInstrument);
    }
}
