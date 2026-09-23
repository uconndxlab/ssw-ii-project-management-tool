<?php

namespace App\Models\Contracts;

use App\Models\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property mixed $program_scope_mode
 * @property-read mixed $programs
 *
 * @phpstan-require-extends Model
 */
interface HasPrograms
{
    /**
     * @return BelongsToMany<Program, covariant Model>
     */
    public function programs(): BelongsToMany;
}
