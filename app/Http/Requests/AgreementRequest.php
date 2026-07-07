<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
            'project_id' => ['nullable', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'abstract' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'extension_start_date' => ['nullable', 'date'],
            'extension_end_date' => ['nullable', 'date', 'after_or_equal:extension_start_date'],
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
        });
    }
}