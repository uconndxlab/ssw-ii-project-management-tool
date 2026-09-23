<?php

namespace App\Models;

use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Support\CarbonDate;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * View: logger or Delivered By (viewers also via a viewable agreement).
 * Edit: logger, participant, or admin on the activity's programs.
 * Delete: logger or that admin, if admin covers all programs.
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected $fillable = [
        'user_id',
        'engagement_date',
        'activity_type_id',
        'completion_count',
        'allotted_duration_hours',
        'allotted_duration_days',
        'internal_only',
        'cancelled',
        'not_yet_complete',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'completion_count' => 'integer',
            'allotted_duration_hours' => 'decimal:1',
            'allotted_duration_days' => 'decimal:1',
            'internal_only' => 'boolean',
            'cancelled' => 'boolean',
            'not_yet_complete' => 'boolean',
        ];
    }

    /** @return HasMany<ActivityLoggingFieldAnswer, $this> */
    public function loggingFieldAnswers(): HasMany
    {
        return $this->hasMany(ActivityLoggingFieldAnswer::class);
    }

    /** @return HasOne<ActivityContactTime, $this> */
    public function contactTime(): HasOne
    {
        return $this->hasOne(ActivityContactTime::class);
    }

    /** @return HasMany<ActivityParticipantTime, $this> */
    public function participantTimes(): HasMany
    {
        return $this->hasMany(ActivityParticipantTime::class);
    }

    /** @return HasMany<DeliverableContribution, $this> */
    public function deliverableContributions(): HasMany
    {
        return $this->hasMany(DeliverableContribution::class);
    }

    /** @return HasMany<AgreementActivityHistory, $this> */
    public function agreementActivityHistories(): HasMany
    {
        return $this->hasMany(AgreementActivityHistory::class);
    }

    /** @return HasMany<ActivityActionLog, $this> */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActivityActionLog::class);
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'activity_agreement')->withTimestamps();
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'activity_organization')->withTimestamps();
    }

    /** @return BelongsToMany<State, $this> */
    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'activity_state')->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ActivityType, $this> */
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

    public function identityLabel(bool $includeType = true): string
    {
        $this->loadMissing(['activityType.contactFamily', 'user']);

        $parts = [];

        if ($includeType) {
            $parts[] = $this->activityType?->name;
        }

        $parts[] = $this->activityType?->contactFamily?->name;
        $parts[] = CarbonDate::parse($this->engagement_date)?->format('M j, Y');

        if (filled($this->user?->name)) {
            $parts[] = 'Logged by '.$this->user->name;
        }

        return collect($parts)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->implode(' · ') ?: 'Activity';
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'activity_program')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_user')->withTimestamps();
    }

    /** @return HasMany<AgreementCertificationCandidate, $this> */
    public function certificationCandidates(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class);
    }

    /** @return HasMany<ActivityAgreementFundingSource, $this> */
    public function agreementFundingSources(): HasMany
    {
        return $this->hasMany(ActivityAgreementFundingSource::class);
    }

    /**
     * Scope: Exclude internal-only activities
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExternalOnly(Builder $query): Builder
    {
        return $query->where('internal_only', false);
    }

    /**
     * Scope: Include only internal activities
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInternalOnly(Builder $query): Builder
    {
        return $query->where('internal_only', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByRecentDisplay(Builder $query): Builder
    {
        return $query
            ->leftJoin('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
            ->select('activities.*')
            ->orderByDesc('activities.engagement_date')
            ->orderBy('activity_types.name')
            ->orderByDesc('activities.id');
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

    public function loggingFieldAnswer(string $contextType, int $fieldId, ?int $contextId = null): ?ActivityLoggingFieldAnswer
    {
        $this->loadMissing('loggingFieldAnswers');

        return $this->loggingFieldAnswers->first(function (ActivityLoggingFieldAnswer $answer) use ($contextType, $fieldId, $contextId) {
            if ((int) $answer->logging_field_id !== $fieldId || $answer->context_type !== $contextType) {
                return false;
            }

            return $contextId === null || (int) $answer->context_id === $contextId;
        });
    }

    public function loggingFieldFileName(string $contextType, int $fieldId, ?int $contextId = null): ?string
    {
        return $this->loggingFieldAnswer($contextType, $fieldId, $contextId)?->originalFileName();
    }

    public function loggingFieldDocumentUrl(string $contextType, int $fieldId, ?int $contextId = null): ?string
    {
        if (! $this->loggingFieldFileName($contextType, $fieldId, $contextId)) {
            return null;
        }

        return route('activities.logging-field-document.download', [
            'activity' => $this,
            'context' => $contextType,
            'fieldId' => $fieldId,
            'agreementId' => $contextType === 'agreement' ? $contextId : null,
        ]);
    }

    /**
     * File inputs are not flashed, so merge stored document paths back into submitted logging values.
     */
    public static function mergePreservedLoggingValues(array $submitted, array $stored): array
    {
        $merged = $submitted;

        foreach ($stored as $key => $value) {
            if (is_array($value)) {
                $merged[$key] = self::mergePreservedLoggingValues(
                    is_array($submitted[$key] ?? null) ? $submitted[$key] : [],
                    $value
                );

                continue;
            }

            $submittedValue = $submitted[$key] ?? null;

            if (($submittedValue === null || $submittedValue === '') && is_string($value) && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
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

    /**
     * @return array<int, array{payor: list<string>, payee: list<string>}>
     */
    public function getFundingSourceValuesAttribute(): array
    {
        $sources = $this->relationLoaded('agreementFundingSources')
            ? $this->agreementFundingSources
            : $this->agreementFundingSources()->get();

        return $sources
            ->groupBy('agreement_id')
            ->map(function ($group) {
                return [
                    ActivityAgreementFundingSource::ROLE_PAYOR => $group
                        ->where('role', ActivityAgreementFundingSource::ROLE_PAYOR)
                        ->map(fn ($row) => $row->token())
                        ->values()
                        ->all(),
                    ActivityAgreementFundingSource::ROLE_PAYEE => $group
                        ->where('role', ActivityAgreementFundingSource::ROLE_PAYEE)
                        ->map(fn ($row) => $row->token())
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }
}
