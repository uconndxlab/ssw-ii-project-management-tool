<?php

namespace App\Http\Requests;

use App\Enums\AgreementTimeTrackingRequirement;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Support\DeliverableHistoryScope;
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
            'organization_payor_source_ids' => ['nullable', 'array'],
            'organization_payor_source_ids.*' => ['distinct', 'exists:organizations,id'],
            'organization_recipient_ids' => ['nullable', 'array'],
            'organization_recipient_ids.*' => ['distinct', 'exists:organizations,id'],
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
            'deliverables.*.program_id' => ['nullable', 'exists:programs,id'],
            'deliverables.*.metric_type' => ['nullable', Rule::in(['time', 'completion'])],
            'deliverables.*.contribution_basis' => ['nullable', Rule::in(['contact', 'user'])],
            'deliverables.*.user_grouping_mode' => ['nullable', Rule::in(['joint', 'individual'])],
            'deliverables.*.include_additional_time' => ['nullable', 'boolean'],
            'deliverables.*.target_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'deliverables.*.suggested_due_date' => ['nullable', 'date'],
            'deliverables.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'deliverables.*.notes' => ['nullable', 'string', 'max:500'],
            'deliverables.*.user_ids' => ['nullable', 'array'],
            'deliverables.*.user_ids.*' => ['exists:users,id'],
            'deliverables.*.team_ids' => ['nullable', 'array'],
            'deliverables.*.team_ids.*' => ['exists:teams,id'],
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
                $previouslySelectedOrganizationIds = collect();
                if ($this->route('agreement') instanceof Agreement) {
                    $previouslySelectedOrganizationIds = $this->route('agreement')
                        ->organizations()
                        ->pluck('organizations.id')
                        ->map(fn ($id) => (int) $id);
                }

                $organizations = Organization::query()
                    ->whereKey($organizationIds)
                    ->with('programs:id')
                    ->get();

                $inactiveOrganizationIds = $organizations
                    ->filter(fn (Organization $organization) => !$organization->active
                        && !$previouslySelectedOrganizationIds->contains((int) $organization->id))
                    ->pluck('id');

                if ($inactiveOrganizationIds->isNotEmpty()) {
                    $validator->errors()->add('organization_ids', 'Inactive organizations cannot be newly added to an agreement.');
                }

                $invalidOrganizationIds = $organizations
                    ->filter(fn (Organization $organization) => !$matchesSelectedPrograms(
                        $organization->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->pluck('id');

                if ($invalidOrganizationIds->isNotEmpty()) {
                    $validator->errors()->add('organization_ids', 'Selected organizations must match one of the selected programs.');
                }
            }

            $selectedOrganizationIdSet = $organizationIds->all();

            $orphanPayorSourceIds = collect($this->input('organization_payor_source_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->reject(fn ($id) => in_array($id, $selectedOrganizationIdSet, true));

            if ($orphanPayorSourceIds->isNotEmpty()) {
                $validator->errors()->add('organization_payor_source_ids', 'Payor source organizations must be selected on the agreement.');
            }

            $orphanRecipientIds = collect($this->input('organization_recipient_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->reject(fn ($id) => in_array($id, $selectedOrganizationIdSet, true));

            if ($orphanRecipientIds->isNotEmpty()) {
                $validator->errors()->add('organization_recipient_ids', 'Recipient organizations must be selected on the agreement.');
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

            $existingAgreement = $this->route('agreement');
            if ($existingAgreement instanceof Agreement) {
                $existingAgreement->loadMissing([
                    'deliverables',
                    'agreementActivityHistories',
                ]);
            }

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

            $deliverableProgramIds = $deliverables
                ->pluck('program_id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($deliverableProgramIds->isNotEmpty()) {
                $invalidDeliverableProgramIds = $deliverableProgramIds
                    ->diff($selectedProgramIdSet)
                    ->values();

                if ($invalidDeliverableProgramIds->isNotEmpty()) {
                    $validator->errors()->add('deliverables', 'Deliverable program filters must belong to the selected agreement programs.');
                }
            }

            $agreementMemberUserIdsViaTeam = collect();
            if ($teamIds->isNotEmpty()) {
                $agreementMemberUserIdsViaTeam = Team::query()
                    ->whereKey($teamIds)
                    ->with(['programs:id', 'users:id'])
                    ->get()
                    ->filter(fn (Team $team) => $matchesSelectedPrograms(
                        $team->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        false
                    ))
                    ->flatMap(fn (Team $team) => $team->users->pluck('id'))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
            }

            foreach ($deliverables as $deliverableIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $existingDeliverable = null;
                if ($existingAgreement instanceof Agreement && !empty($row['id'])) {
                    $existingDeliverable = $existingAgreement->deliverables->firstWhere('id', (int) $row['id']);
                }

                if (empty($row['contact_family_id'])) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.contact_family_id", 'Deliverables must select a contact family.');
                }

                $contactFamily = null;
                if (!empty($row['contact_family_id'])) {
                    $contactFamily = ContactFamily::query()->find((int) $row['contact_family_id']);
                }

                if (!empty($row['activity_type_id']) && $contactFamily) {
                    $activityType = ActivityType::query()->find((int) $row['activity_type_id']);

                    if ($activityType && (int) $activityType->contact_family_id !== (int) $contactFamily->id) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.activity_type_id", 'Deliverable activity types must belong to the selected contact family.');
                    }
                }

                $deliverableClassificationChanged = $existingDeliverable
                    && $this->deliverableClassificationChanged($existingDeliverable, $row);
                $deliverableScopeHasHistory = $existingDeliverable
                    && $this->deliverableScopeHasHistory($existingAgreement?->agreementActivityHistories ?? collect(), $existingDeliverable);

                $metricType = $row['metric_type'] ?? null;
                $contributionBasis = $row['contribution_basis'] ?? null;
                $groupingMode = $row['user_grouping_mode'] ?? null;
                $deliverableUserIds = collect($row['user_ids'] ?? [])
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                $deliverableTeamIds = collect($row['team_ids'] ?? [])
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if (!$metricType) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.metric_type", 'Deliverable metric type is required.');
                }

                if (!$contributionBasis) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.contribution_basis", 'Deliverable contribution basis is required.');
                }

                if ($contributionBasis === 'contact') {
                    if ($groupingMode) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.user_grouping_mode", 'Contact-based deliverables cannot define a user grouping mode.');
                    }

                    if ($deliverableUserIds->isNotEmpty() || $deliverableTeamIds->isNotEmpty()) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}", 'Contact-based deliverables cannot select users or teams.');
                    }
                }

                if ($contributionBasis === 'user' && !$groupingMode) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.user_grouping_mode", 'User-based deliverables must choose joint or individual grouping.');
                }

                if ($contributionBasis === 'user' && $deliverableUserIds->isEmpty() && $deliverableTeamIds->isEmpty()) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}", 'User-based deliverables must select at least one user or team.');
                }

                if ($groupingMode === 'individual') {
                    if ($deliverableTeamIds->isNotEmpty()) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.team_ids", 'Individual deliverables cannot link teams directly. Assign users instead.');
                    }

                    if ($deliverableUserIds->isEmpty()) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.user_ids", 'Individual deliverables must select at least one user.');
                    }
                }

                if (($row['include_additional_time'] ?? false) && $metricType !== 'time') {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.include_additional_time", 'Only time deliverables can include prep and follow up time.');
                }

                if (($row['include_additional_time'] ?? false) && $contactFamily && !$contactFamily->track_additional_time) {
                    $validator->errors()->add("deliverables.{$deliverableIndex}.include_additional_time", 'The selected contact family does not track prep and follow up time.');
                }

                if ($deliverableUserIds->isNotEmpty()) {
                    $invalidDeliverableUserIds = User::query()
                        ->whereKey($deliverableUserIds)
                        ->with('programs:id')
                        ->get()
                        ->filter(function (User $user) use ($matchesSelectedPrograms, $agreementMemberUserIdsViaTeam) {
                            if ($agreementMemberUserIdsViaTeam->contains((int) $user->id)) {
                                return false;
                            }

                            return !$matchesSelectedPrograms(
                                $user->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                                false
                            );
                        })
                        ->pluck('id');

                    if ($invalidDeliverableUserIds->isNotEmpty()) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.user_ids", 'Deliverable users must match one of the selected programs.');
                    }
                }

                if ($deliverableTeamIds->isNotEmpty()) {
                    $invalidDeliverableTeamIds = Team::query()
                        ->whereKey($deliverableTeamIds)
                        ->with('programs:id')
                        ->get()
                        ->filter(fn (Team $team) => !$matchesSelectedPrograms(
                            $team->programs->pluck('id')->map(fn ($id) => (int) $id)->values(),
                            false
                        ))
                        ->pluck('id');

                    if ($invalidDeliverableTeamIds->isNotEmpty()) {
                        $validator->errors()->add("deliverables.{$deliverableIndex}.team_ids", 'Deliverable teams must match one of the selected programs.');
                    }
                }

                if ($existingDeliverable && $deliverableScopeHasHistory) {
                    if ($deliverableClassificationChanged) {
                        $validator->errors()->add(
                            "deliverables.{$deliverableIndex}",
                            'Deliverable classification cannot change in place after activity history exists. Create a new deliverable instead.'
                        );
                    }

                    if ($this->deliverableSemanticFieldsChanged($existingDeliverable, $row)) {
                        $validator->errors()->add(
                            "deliverables.{$deliverableIndex}",
                            'Deliverable counting rules cannot change in place after activity history exists. Create a new deliverable instead.'
                        );
                    }
                }
            }
        });
    }

    private function deliverableClassificationChanged(AgreementDeliverable $deliverable, array $row): bool
    {
        return (int) ($deliverable->contact_family_id ?? 0) !== (int) ($row['contact_family_id'] ?? 0)
            || (int) ($deliverable->activity_type_id ?? 0) !== (int) ($row['activity_type_id'] ?? 0)
            || (int) ($deliverable->program_id ?? 0) !== (int) ($row['program_id'] ?? 0);
    }

    private function deliverableScopeHasHistory(Collection $histories, AgreementDeliverable $deliverable): bool
    {
        return DeliverableHistoryScope::hasMatchingHistory($histories, $deliverable);
    }

    private function deliverableSemanticFieldsChanged(AgreementDeliverable $deliverable, array $row): bool
    {
        return ($deliverable->metric_type ?? null) !== ($row['metric_type'] ?? null)
            || ($deliverable->contribution_basis ?? null) !== ($row['contribution_basis'] ?? null)
            || ($deliverable->user_grouping_mode ?? null) !== ($row['user_grouping_mode'] ?? null)
            || (bool) $deliverable->include_additional_time !== filter_var($row['include_additional_time'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function isDeletedRow(array $row): bool
    {
        return filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
