<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
            'original_end_date' => ['nullable', 'date'],
            'extended_end_date' => ['nullable', 'date', 'after_or_equal:original_end_date'],
            'certification_candidates' => ['nullable', 'string'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],

            'agreement_logging_field_ids' => ['nullable', 'array'],
            'agreement_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_agreement_logging_field_ids' => ['nullable', 'array'],
            'required_agreement_logging_field_ids.*' => ['exists:logging_fields,id'],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,txt'],
        ];
    }
}