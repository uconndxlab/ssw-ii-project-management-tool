<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\AgreementAttachment;
use App\Models\AgreementDeliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgreementDuplicationService
{
    public function duplicate(Agreement $source): Agreement
    {
        $source->load([
            'organizations',
            'states',
            'programs',
            'users',
            'teams',
            'principalInvestigators',
            'agreementLoggingFields',
            'certificationCandidates',
            'attachments',
            'deliverables' => fn ($query) => $query
                ->whereNull('retired_at')
                ->with(['users', 'teams']),
        ]);

        return DB::transaction(function () use ($source) {
            $copy = Agreement::create([
                'name' => $this->buildCopyName($source->name),
                'active' => $source->active,
                'abstract' => $source->abstract,
                'start_date' => $source->start_date,
                'end_date' => $source->end_date,
                'extension_start_date' => $source->extension_start_date,
                'extension_end_date' => $source->extension_end_date,
                'time_tracking_mode' => $source->time_tracking_mode,
                'require_payor' => $source->require_payor,
                'require_payee' => $source->require_payee,
            ]);

            $copy->organizations()->sync(
                $source->organizations
                    ->mapWithKeys(fn ($organization) => [
                        $organization->id => [
                            'payor_source' => (bool) $organization->pivot->payor_source,
                            'recipient' => (bool) $organization->pivot->recipient,
                        ],
                    ])
                    ->all()
            );
            $copy->states()->sync($source->states->pluck('id')->all());
            $copy->programs()->sync($source->programs->pluck('id')->all());
            $copy->users()->sync($source->users->pluck('id')->all());
            $copy->teams()->sync($source->teams->pluck('id')->all());
            $copy->principalInvestigators()->sync($source->principalInvestigators->pluck('id')->all());

            $loggingFieldSync = $source->agreementLoggingFields
                ->mapWithKeys(fn ($field) => [
                    $field->id => ['is_required' => (bool) $field->pivot->is_required],
                ])
                ->all();
            $copy->agreementLoggingFields()->sync($loggingFieldSync);

            foreach ($source->certificationCandidates as $candidate) {
                $copy->certificationCandidates()->create([
                    'name' => $candidate->name,
                    'program_id' => $candidate->program_id,
                    'notes' => $candidate->notes,
                ]);
            }

            foreach ($source->attachments as $attachment) {
                $this->copyAttachment($copy, $attachment);
            }

            foreach ($source->deliverables as $deliverable) {
                $this->copyDeliverable($copy, $deliverable);
            }

            return $copy->fresh([
                'organizations',
                'states',
                'programs.projects',
                'users',
                'teams',
                'deliverables',
            ]);
        });
    }

    private function buildCopyName(string $name): string
    {
        $baseName = preg_replace('/\s+\(Copy(?:\s+\d+)?\)$/', '', $name) ?: $name;
        $candidate = $baseName . ' (Copy)';
        $counter = 2;

        while (Agreement::query()->where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (Copy ' . $counter . ')';
            $counter++;
        }

        return $candidate;
    }

    private function copyAttachment(Agreement $copy, AgreementAttachment $attachment): void
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($attachment->file_path)) {
            return;
        }

        $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';
        $newPath = 'agreement-attachments/' . uniqid('copy_', true) . $suffix;

        $disk->copy($attachment->file_path, $newPath);

        $copy->attachments()->create([
            'filename' => $attachment->filename,
            'file_path' => $newPath,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
        ]);
    }

    private function copyDeliverable(Agreement $copy, AgreementDeliverable $deliverable): void
    {
        $newDeliverable = $copy->deliverables()->create([
            'activity_type_id' => $deliverable->activity_type_id,
            'contact_family_id' => $deliverable->contact_family_id,
            'program_id' => $deliverable->program_id,
            'metric_type' => $deliverable->metric_type,
            'time_basis' => $deliverable->time_basis,
            'allotted_time_unit' => $deliverable->allotted_time_unit,
            'contribution_basis' => $deliverable->contribution_basis,
            'user_grouping_mode' => $deliverable->user_grouping_mode,
            'include_additional_time' => (bool) $deliverable->include_additional_time,
            'target_quantity' => $deliverable->target_quantity,
            'suggested_due_date' => $deliverable->suggested_due_date,
            'sort_order' => $deliverable->sort_order,
            'notes' => $deliverable->notes,
            'retired_at' => null,
        ]);

        foreach ($deliverable->teams as $team) {
            if ($team->pivot->unassigned_at) {
                continue;
            }

            $newDeliverable->teams()->attach($team->id, [
                'assigned_at' => null,
                'unassigned_at' => null,
            ]);
        }

        foreach ($deliverable->users as $user) {
            if ($user->pivot->unassigned_at) {
                continue;
            }

            $newDeliverable->users()->attach($user->id, [
                'assigned_at' => null,
                'unassigned_at' => null,
                'source_team_id' => $user->pivot->source_team_id,
            ]);
        }
    }
}
