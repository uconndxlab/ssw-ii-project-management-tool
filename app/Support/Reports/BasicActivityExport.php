<?php

namespace App\Support\Reports;

use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use App\Models\Agreement;
use App\Models\LoggingField;
use Illuminate\Database\Eloquent\Builder;

class BasicActivityExport
{
    public function __construct(private Agreement $agreement) {}

    /** @return Builder<Activity> */
    private function activities(): Builder
    {
        return Activity::query()
            ->whereHas('agreements', fn (Builder $query) => $query->where('agreements.id', $this->agreement->id));
    }

    public function write(): void
    {
        $fields = LoggingField::query()
            ->whereIn('id', ActivityLoggingFieldAnswer::query()
                ->select('logging_field_id')
                ->whereIn('activity_id', $this->activities()->select('activities.id')))
            ->ordered()->orderBy('id')->get();

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Unable to open CSV output.');
        }

        try {
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeRow($output, array_merge([
                'Date', 'Activity family', 'Activity type', 'Agreements covered', 'Organizations',
                'Completions', 'Logged by', 'Internal only', 'Cancelled', 'Not yet complete',
            ], $fields->map(fn (LoggingField $field): string => $field->name)->values()->all()));

            $activities = $this->activities()
                ->with(['activityType.contactFamily', 'agreements', 'organizations', 'user', 'loggingFieldAnswers'])
                ->orderBy('engagement_date')->orderBy('activities.id')->lazy(500);

            foreach ($activities as $activity) {
                $answers = $activity->loggingFieldAnswers->groupBy('logging_field_id');
                $row = [
                    $activity->engagement_date->format('Y-m-d'),
                    $activity->activityType?->contactFamily->name ?? '',
                    $activity->activityType->name ?? '',
                    $activity->agreements->sortBy('name')->pluck('name')->implode('; '),
                    $activity->organizations->sortBy('name')->pluck('name')->implode('; '),
                    (string) $activity->completion_count,
                    $activity->user->name ?? '',
                    $activity->internal_only ? 'Yes' : 'No',
                    $activity->cancelled ? 'Yes' : 'No',
                    $activity->not_yet_complete ? 'Yes' : 'No',
                ];

                foreach ($fields as $field) {
                    $row[] = $answers->get($field->id, collect())
                        ->sortBy('id')
                        ->map(fn (ActivityLoggingFieldAnswer $answer) => $this->answerValue($answer, $field))
                        ->implode('; ');
                }

                $this->writeRow($output, $row);
            }
        } finally {
            fclose($output);
        }
    }

    private function answerValue(ActivityLoggingFieldAnswer $answer, LoggingField $field): string
    {
        if ($field->field_type === 'document') {
            return $answer->originalFileName() ?? '';
        }

        $value = $answer->value;
        if (is_array($value)) {
            $labels = $field->optionLabelMap();

            return implode('; ', array_map(fn ($selected) => $labels[(string) $selected] ?? (string) $selected, $value));
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $row
     */
    private function writeRow($output, array $row): void
    {
        $safe = array_map(fn (string $cell) => preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell, $row);
        fputcsv($output, $safe, ',', '"', '', "\r\n");
    }
}
