<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property bool $is_required
 * @property int $sort_order
 */
class AgreementLoggingFieldPivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
