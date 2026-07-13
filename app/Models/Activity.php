<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'engagement_date',
        'activity_type_id',
        'internal_only',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'internal_only' => 'boolean',
        ];
    }

    public function loggingFieldAnswers(): HasMany
    {
        return $this->hasMany(ActivityLoggingFieldAnswer::class);
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'activity_agreement')->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'activity_organization')->withTimestamps();
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'activity_state')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /**
     * Convenience accessor to get contact family through activity type
     */
    public function contactFamily()
    {
        return $this->activityType?->contactFamily;
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'activity_program')->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'activity_project')->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_user')->withTimestamps();
    }

    public function certificationCandidates(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class);
    }

    /**
     * Scope: Exclude internal-only activities
     */
    public function scopeExternalOnly($query)
    {
        return $query->where('internal_only', false);
    }

    /**
     * Scope: Include only internal activities
     */
    public function scopeInternalOnly($query)
    {
        return $query->where('internal_only', true);
    }

    public function getAgreementLoggingValuesAttribute(): array
    {
        return $this->buildLoggingFieldValueMap('agreement');
    }

    public function getContactFamilyLoggingValuesAttribute(): array
    {
        return $this->buildLoggingFieldValueMap('contact_family');
    }

    public function getActivityTypeLoggingValuesAttribute(): array
    {
        return $this->buildLoggingFieldValueMap('activity_type');
    }

    private function buildLoggingFieldValueMap(string $contextType): array
    {
        $answers = $this->relationLoaded('loggingFieldAnswers')
            ? $this->loggingFieldAnswers
            : $this->loggingFieldAnswers()->get();

        $filtered = $answers->where('context_type', $contextType);

        if ($contextType === 'agreement') {
            return $filtered
                ->groupBy('context_id')
                ->map(function ($group) {
                    return $group
                        ->mapWithKeys(fn ($answer) => [$answer->logging_field_id => $answer->value])
                        ->all();
                })
                ->all();
        }

        return $filtered
            ->mapWithKeys(fn ($answer) => [$answer->logging_field_id => $answer->value])
            ->all();
    }

}
