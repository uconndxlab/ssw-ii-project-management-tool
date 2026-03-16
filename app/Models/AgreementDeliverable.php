<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementDeliverable extends Model
{
    protected $fillable = [
        'agreement_id',
        'activity_type_id',
        'contact_family_id',
        'required_hours',
        'required_activities',
        'notes',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }
}
