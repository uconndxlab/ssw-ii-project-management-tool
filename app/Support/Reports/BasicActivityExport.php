<?php

namespace App\Support\Reports;

use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use App\Models\Agreement;
use App\Models\LoggingField;
use Illuminate\Database\Eloquent\Builder;

class BasicActivityExport
{
    /** Where a logging answer was asked, in column order. Shown after the field name in each header. */
    private const SOURCES = [
        'agreement' => 'This agreement',
        'contact_family' => 'Activity family',
        'activity_type' => 'Activity type',
        'other_agreement' => 'Other agreements',
    ];

    public function __construct(private Agreement $agreement) {}

    /** @return Builder<Activity> */
    private function activities(): Builder
    {
        return Activity::query()
            ->whereHas('agreements', fn (Builder $query) => $query->where('agreements.id', $this->agreement->id));
    }

    public function write(): void
    {
        $answered = ActivityLoggingFieldAnswer::query()
            ->whereIn('activity_id', $this->activities()->select('activities.id'))
            ->select(['logging_field_id', 'context_type', 'context_id'])->distinct()->get()
            ->map(fn (ActivityLoggingFieldAnswer $answer): string => $this->columnKey($answer))
            ->flip();
        $fields = LoggingField::query()
            ->whereIn('id', $answered->keys()->map(fn (string $key): int => (int) explode(':', $key)[1]))
            ->ordered()->orderBy('id')->get();
        $columns = collect(array_keys(self::SOURCES))->flatMap(fn (string $source) => $fields
            ->filter(fn (LoggingField $field): bool => $answered->has($source.':'.$field->id))
            ->map(fn (LoggingField $field): array => [$source, $field]))->values();

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Unable to open CSV output.');
        }

        try {
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeRow($output, array_merge([
                'Date', 'Activity family', 'Activity type', 'Agreements covered', 'Organizations',
                'Completions', 'Logged by', 'Internal only', 'Cancelled', 'Not yet complete',
            ], $columns->map(fn (array $column): string => $column[1]->name.' ('.self::SOURCES[$column[0]].')')->all()));

            $activities = $this->activities()
                ->with(['activityType.contactFamily', 'agreements', 'organizations', 'user', 'loggingFieldAnswers'])
                ->orderBy('engagement_date')->orderBy('activities.id')->lazy(500);

            foreach ($activities as $activity) {
                $answers = $activity->loggingFieldAnswers->groupBy(fn (ActivityLoggingFieldAnswer $answer): string => $this->columnKey($answer));
                $agreementNames = $activity->agreements->pluck('name', 'id');
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

                foreach ($columns as [$source, $field]) {
                    $row[] = $answers->get($source.':'.$field->id, collect())
                        ->sortBy('id')
                        ->map(fn (ActivityLoggingFieldAnswer $answer): string => $source === 'other_agreement'
                            ? ($agreementNames[$answer->context_id] ?? 'Removed agreement').': '.$this->answerValue($answer, $field)
                            : $this->answerValue($answer, $field))
                        ->implode('; ');
                }

                $this->writeRow($output, $row);
            }
        } finally {
            fclose($output);
        }
    }

    /** Answers on another covered agreement are kept apart from this agreement's own fields. */
    private function columnKey(ActivityLoggingFieldAnswer $answer): string
    {
        $source = $answer->context_type === 'agreement' && $answer->context_id !== $this->agreement->id
            ? 'other_agreement'
            : $answer->context_type;

        return $source.':'.$answer->logging_field_id;
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
