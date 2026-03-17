<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementCertificationCandidate extends Model
{
    protected $fillable = [
        'agreement_id',
        'name',
        'program_id',
        'notes',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}