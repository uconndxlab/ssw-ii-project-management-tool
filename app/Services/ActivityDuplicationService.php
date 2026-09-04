<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use Illuminate\Support\Facades\DB;

class ActivityDuplicationService
{
    public function __construct(
        private DeliverableContributionService $deliverableContributionService,
        private PrivateFileService $privateFiles,
    ) {}

    public function duplicate(Activity $source, int $userId): Activity
    {
        $source->load([
            'agreements',
            'states',
            'organizations',
            'programs',
            'participants',
            'contactTime',
            'participantTimes',
            'agreementFundingSources',
            'loggingFieldAnswers',
        ]);

        return DB::transaction(function () use ($source, $userId) {
            $copy = Activity::create([
                'user_id' => $userId,
                'engagement_date' => $source->engagement_date,
                'activity_type_id' => $source->activity_type_id,
                'completion_count' => $source->completion_count,
                'allotted_duration_hours' => $source->allotted_duration_hours,
                'allotted_duration_days' => $source->allotted_duration_days,
                'internal_only' => $source->internal_only,
                'cancelled' => $source->cancelled,
                'not_yet_complete' => $source->not_yet_complete,
            ]);

            $copy->agreements()->sync($source->agreements->pluck('id')->all());
            $copy->states()->sync($source->states->pluck('id')->all());
            $copy->organizations()->sync($source->organizations->pluck('id')->all());
            $copy->programs()->sync($source->programs->pluck('id')->all());
            $copy->participants()->sync($source->participants->pluck('id')->all());

            if ($source->contactTime) {
                $copy->contactTime()->create([
                    'activity_hours' => $source->contactTime->activity_hours,
                    'prep_hours' => $source->contactTime->prep_hours,
                    'follow_up_hours' => $source->contactTime->follow_up_hours,
                ]);
            }

            foreach ($source->participantTimes as $participantTime) {
                $copy->participantTimes()->create([
                    'user_id' => $participantTime->user_id,
                    'participant_name' => $participantTime->participant_name,
                    'hours' => $participantTime->hours,
                    'prep_hours' => $participantTime->prep_hours,
                    'follow_up_hours' => $participantTime->follow_up_hours,
                    'notes' => $participantTime->notes,
                ]);
            }

            foreach ($source->agreementFundingSources as $fundingSource) {
                $copy->agreementFundingSources()->create([
                    'agreement_id' => $fundingSource->agreement_id,
                    'role' => $fundingSource->role,
                    'source_type' => $fundingSource->source_type,
                    'source_id' => $fundingSource->source_id,
                ]);
            }

            foreach ($source->loggingFieldAnswers as $answer) {
                $this->copyLoggingFieldAnswer($copy, $answer);
            }

            $this->deliverableContributionService->syncForActivity($copy);

            return $copy->fresh([
                'agreements',
                'states',
                'organizations',
                'programs',
                'participants',
                'contactTime',
                'participantTimes',
                'agreementFundingSources',
                'loggingFieldAnswers',
                'user',
                'activityType',
            ]);
        });
    }

    private function copyLoggingFieldAnswer(Activity $copy, ActivityLoggingFieldAnswer $answer): void
    {
        $filePath = $answer->file_path;

        if ($filePath !== null) {
            if (! $this->privateFiles->exists($filePath)) {
                return;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $suffix = $extension !== '' ? '.'.$extension : '';
            $newPath = 'activity-documents/'.uniqid('copy_', true).$suffix;
            $this->privateFiles->copy($filePath, $newPath);
            $filePath = $newPath;
        }

        $copy->loggingFieldAnswers()->create([
            'logging_field_id' => $answer->logging_field_id,
            'context_type' => $answer->context_type,
            'context_id' => $answer->context_id,
            'value_text' => $answer->value_text,
            'value_json' => $answer->value_json,
            'value_number' => $answer->value_number,
            'value_boolean' => $answer->value_boolean,
            'file_path' => $filePath,
            'file_name' => $answer->file_name,
        ]);
    }
}
