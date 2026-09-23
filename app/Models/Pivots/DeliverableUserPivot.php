<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string|null $assigned_at
 * @property string|null $unassigned_at
 * @property int|null $source_team_id
 * @property string|null $target_quantity
 */
class DeliverableUserPivot extends Pivot
{
    //
}
