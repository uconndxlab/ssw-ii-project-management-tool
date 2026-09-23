<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property bool $payor_source
 * @property bool $recipient
 */
class AgreementOrganizationPivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'payor_source' => 'boolean',
            'recipient' => 'boolean',
        ];
    }
}
