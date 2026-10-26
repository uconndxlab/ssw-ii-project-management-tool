<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCertificationDutiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->access()->hasAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $programRules = ['integer', 'distinct', 'exists:programs,id'];
        $systemRules = ['sometimes', 'boolean'];
        if (! $this->user()?->access()->isSystemAdmin()) {
            $programRules[] = Rule::in($this->user()?->access()->adminProgramIds() ?? []);
            $systemRules[] = Rule::in([false, 0, '0']);
        }

        return [
            'duty_coach_present' => ['sometimes', 'boolean'],
            'duty_coach_system' => $systemRules,
            'duty_coach_program_ids' => ['sometimes', 'array'],
            'duty_coach_program_ids.*' => $programRules,
            'duty_manager_present' => ['sometimes', 'boolean'],
            'duty_manager_system' => $systemRules,
            'duty_manager_program_ids' => ['sometimes', 'array'],
            'duty_manager_program_ids.*' => $programRules,
        ];
    }
}
