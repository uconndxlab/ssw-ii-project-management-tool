<?php

namespace App\Http\Requests;

use App\Enums\AgreementTimeTrackingRequirement;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organization_ids' => ['nullable', 'array'],
            'organization_ids.*' => ['exists:organizations,id'],
            'state_ids' => ['nullable', 'array'],
            'state_ids.*' => ['exists:states,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'abstract' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'extension_start_date' => ['nullable', 'date'],
            'extension_end_date' => ['nullable', 'date', 'after_or_equal:extension_start_date'],
            'time_tracking_mode' => ['required', Rule::in(array_merge(['none'], AgreementTimeTrackingRequirement::values()))],
            'certification_candidates' => ['nullable', 'array'],
            'certification_candidates.*.id' => ['nullable', 'integer'],
            'certification_candidates.*.value' => ['nullable', 'string', 'max:255'],
            'certification_candidates.*._delete' => ['nullable', 'boolean'],
            'deleted_attachment_ids' => ['nullable', 'array'],
            'deleted_attachment_ids.*' => ['integer', 'exists:agreement_attachments,id'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['distinct', 'exists:users,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['distinct', 'exists:teams,id'],
            'principal_investigator_ids' => ['nullable', 'array'],
            'principal_investigator_ids.*' => ['distinct', 'exists:users,id'],

            'agreement_logging_field_ids' => ['nullable', 'array'],
            'agreement_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_agreement_logging_field_ids' => ['nullable', 'array'],
            'required_agreement_logging_field_ids.*' => ['exists:logging_fields,id'],

            'deliverables' => ['nullable', 'array'],
            'deliverables.*.id' => ['nullable', 'integer'],
            'deliverables.*.activity_type_id' => ['nullable', 'exists:activity_types,id'],
            'deliverables.*.contact_family_id' => ['nullable', 'exists:contact_families,id'],
            'deliverables.*.required_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'deliverables.*.required_activities' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'deliverables.*.notes' => ['nullable', 'string', 'max:500'],
            'deliverables.*.user_ids' => ['nullable', 'array'],
            'deliverables.*.user_ids.*' => ['exists:users,id'],
            'deliverables.*._delete' => ['nullable', 'boolean'],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,txt'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $projectIds = collect($this->input('project_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $programIds = collect($this->input('program_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($programIds->isNotEmpty() && $projectIds->isEmpty()) {
                $validator->errors()->add('project_ids', 'Select at least one project before assigning programs.');

                return;
            }

            if ($programIds->isNotEmpty()) {
                $programProjectIds = Program::query()
                    ->whereKey($programIds)
                    ->pluck('project_id', 'id');

                $invalidPrograms = $programProjectIds
                    ->filter(fn ($projectId) => !$projectIds->contains((int) $projectId))
                    ->keys();

                if ($invalidPrograms->isNotEmpty()) {
                    $validator->errors()->add('program_ids', 'Each selected program must belong to one of the selected projects.');

                    return;
                }
            }

            $selectedProgramIdSet = $programIds->map(fn ($id) => (int) $id)->values();

            $matchesSelectedPrograms = function (Collection $scopedProgramIds, bool $allowGlobal) use ($selectedProgramIdSet): bool {
                if ($scopedProgramIds->isEmpty()) {
                    return $allowGlobal;
                }

                if ($selectedProgramIdSet->isEmpty()) {
                    return false;
                }

                return $scopedProgramIds->intersect($selectedProgramIdSet)->isNotEmpty();
            };

            $organizationIds = collect($this->input('organization_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($organizationIds->isNotEmpty()) {
                $invalidOrganizationIds = Organization::query()
                    ->whereKey($organizationIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (Organization $organization) => !$matchesSelectedPrograms(
                        $organization->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->pluck('id');

                if ($invalidOrganizationIds->isNotEmpty()) {
                    $validator->errors()->add('organization_ids', 'Selected organizations must match one of the selected programs.');
                }
            }

            $directUserIds = collect($this->input('user_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $teamIds = collect($this->input('team_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($teamIds->isNotEmpty()) {
                $invalidTeamIds = Team::query()
                    ->whereKey($teamIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (Team $team) => !$matchesSelectedPrograms(
                        $team->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->pluck('id');

                if ($invalidTeamIds->isNotEmpty()) {
                    $validator->errors()->add('team_ids', 'Selected teams must match one of the selected programs.');
                }
            }

            if ($directUserIds->isNotEmpty()) {
                $invalidDirectUserIds = User::query()
                    ->whereKey($directUserIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (User $user) => !$matchesSelectedPrograms(
                        $user->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->pluck('id');

                if ($invalidDirectUserIds->isNotEmpty()) {
                    $validator->errors()->add('user_ids', 'Selected users must match one of the selected programs.');
                }
            }

            $teamUserIds = Team::query()
                ->whereKey($teamIds)
                ->with(['users:id'])
                ->get()
                ->flatMap(fn (Team $team) => $team->users->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $effectiveUserIds = $directUserIds
                ->merge($teamUserIds)
                ->unique()
                ->values();

            if ($effectiveUserIds->isEmpty()) {
                $validator->errors()->add('team_ids', 'Add at least one user or team to the agreement.');
            }

            $principalInvestigatorIds = collect($this->input('principal_investigator_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($principalInvestigatorIds->isEmpty()) {
                $validator->errors()->add('principal_investigator_ids', 'Select at least one principal investigator. Use the PI toggle on a selected user.');

                return;
            }

            if ($principalInvestigatorIds->diff($effectiveUserIds)->isNotEmpty()) {
                $validator->errors()->add('principal_investigator_ids', 'Principal investigators must be selected from the users assigned to this agreement.');
            }

            $agreementLoggingFieldIds = collect($this->input('agreement_logging_field_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($agreementLoggingFieldIds->isNotEmpty()) {
                $invalidLoggingFieldIds = LoggingField::query()
                    ->whereKey($agreementLoggingFieldIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (LoggingField $field) => !$matchesSelectedPrograms(
                        $field->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        true
                    ))
                    ->pluck('id');

                if ($invalidLoggingFieldIds->isNotEmpty()) {
                    $validator->errors()->add('agreement_logging_field_ids', 'Selected logging fields must be global or match one of the selected programs.');
                }
            }

            $deliverables = collect($this->input('deliverables', []))
                ->filter(fn ($row) => is_array($row) && empty($row['_delete']))
                ->values();

            $deliverableContactFamilyIds = $deliverables
                ->pluck('contact_family_id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($deliverableContactFamilyIds->isNotEmpty()) {
                $invalidContactFamilyIds = ContactFamily::query()
                    ->whereKey($deliverableContactFamilyIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (ContactFamily $family) => !$matchesSelectedPrograms(
                        $family->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        true
                    ))
                    ->pluck('id');

                if ($invalidContactFamilyIds->isNotEmpty()) {
                    $validator->errors()->add('deliverables', 'Deliverable contact families must be global or match one of the selected programs.');
                }
            }

            $deliverableActivityTypeIds = $deliverables
                ->pluck('activity_type_id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($deliverableActivityTypeIds->isNotEmpty()) {
                $invalidActivityTypeIds = ActivityType::query()
                    ->whereKey($deliverableActivityTypeIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (ActivityType $type) => !$matchesSelectedPrograms(
                        $type->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        true
                    ))
                    ->pluck('id');

                if ($invalidActivityTypeIds->isNotEmpty()) {
                    $validator->errors()->add('deliverables', 'Deliverable activity types must be global or match one of the selected programs.');
                }
            }

            $deliverableUserIds = $deliverables
                ->flatMap(fn (array $row) => collect($row['user_ids'] ?? []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($deliverableUserIds->isNotEmpty()) {
                $invalidDeliverableUserIds = User::query()
                    ->whereKey($deliverableUserIds)
                    ->with('programs:id')
                    ->get()
                    ->filter(fn (User $user) => !$matchesSelectedPrograms(
                        $user->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->pluck('id');

                if ($invalidDeliverableUserIds->isNotEmpty()) {
                    $validator->errors()->add('deliverables', 'Deliverable assigned users must match one of the selected programs.');
                }
            }
        });
    }
}
