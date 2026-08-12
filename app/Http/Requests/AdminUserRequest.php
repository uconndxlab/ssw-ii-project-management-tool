<?php

namespace App\Http\Requests;

use App\Support\ProjectProgramScope;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function messages(): array
    {
        return [
            'po_number.regex' => 'The PO number must be exactly 6 digits.',
            'po_number.size' => 'The PO number must be exactly 6 digits.',
            'po_number.unique' => 'This PO number is already assigned to another user.',
        ];
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'po_number' => [
                'nullable',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
                Rule::unique('users', 'po_number')->ignore($userId),
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', Password::defaults()],
            'role' => ['required', 'in:admin,staff,consultant'],
            'active' => ['nullable', 'boolean'],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->route('user');

            if (!$this->boolean('active')) {
                if ($user instanceof User && Auth::id() === $user->id) {
                    $validator->errors()->add('active', 'You cannot deactivate your own user account.');
                }

                if ($user instanceof User && $user->isAdmin() && $user->isActive()) {
                    $otherActiveAdmins = User::query()
                        ->where('role', 'admin')
                        ->where('active', true)
                        ->whereKeyNot($user->id)
                        ->exists();

                    if (!$otherActiveAdmins) {
                        $validator->errors()->add('active', 'You cannot deactivate the last active administrator.');
                    }
                }
            }

            if ($user instanceof User) {
                $supervisorId = $this->input('supervisor_id');

                if ($supervisorId !== null && $supervisorId !== '') {
                    if ($this->wouldCreateCircularReference($user, (int) $supervisorId)) {
                        $validator->errors()->add('supervisor_id', 'This supervisor assignment would create a circular reporting structure.');
                    }
                }
            } else {
                $supervisorId = $this->input('supervisor_id');

                if ($supervisorId !== null && $supervisorId !== '') {
                    $supervisor = User::query()->find((int) $supervisorId);

                    if ($supervisor && !$supervisor->isActive()) {
                        $validator->errors()->add('supervisor_id', 'The selected supervisor is inactive.');
                    }
                }
            }

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
                ProjectProgramScope::validateSelection(
                    $validator,
                    $projectIds->all(),
                    $programIds->all()
                );
            }
        });
    }

    private function wouldCreateCircularReference(User $user, int $supervisorId, int $maxDepth = 50): bool
    {
        if ($user->id === $supervisorId) {
            return true;
        }

        $currentSupervisorId = $supervisorId;
        $depth = 0;

        while ($currentSupervisorId !== null && $depth < $maxDepth) {
            $supervisor = User::query()->find($currentSupervisorId);

            if (!$supervisor) {
                return false;
            }

            if (!$supervisor->isActive()) {
                return false;
            }

            if ($supervisor->supervisor_id === $user->id) {
                return true;
            }

            $currentSupervisorId = $supervisor->supervisor_id;
            $depth++;
        }

        return false;
    }
}
