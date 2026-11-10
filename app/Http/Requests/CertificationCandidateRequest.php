<?php

namespace App\Http\Requests;

use App\Models\CertificationCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificationCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('certificationCandidate')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $candidate = $this->route('certificationCandidate');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(CertificationCandidate::class)->ignore($candidate)],
            'phone' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
