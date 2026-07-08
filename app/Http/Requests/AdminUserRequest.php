<?php

namespace App\Http\Requests;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', Password::defaults()],
            'role' => ['required', 'in:admin,staff,consultant'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
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

            if (!$user instanceof User) {
                return;
            }

            $supervisorId = $this->input('supervisor_id');

            if ($supervisorId === null || $supervisorId === '') {
                return;
            }

            if ($this->wouldCreateCircularReference($user, (int) $supervisorId)) {
                $validator->errors()->add('supervisor_id', 'This supervisor assignment would create a circular reporting structure.');
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
                $programProjectIds = Program::query()
                    ->whereKey($programIds)
                    ->pluck('project_id', 'id');

                $invalidPrograms = $programProjectIds
                    ->filter(fn ($projectId) => !$projectIds->contains((int) $projectId))
                    ->keys();

                if ($invalidPrograms->isNotEmpty()) {
                    $validator->errors()->add('program_ids', 'Each selected program must belong to one of the selected projects.');
                }
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

            if ($supervisor->supervisor_id === $user->id) {
                return true;
            }

            $currentSupervisorId = $supervisor->supervisor_id;
            $depth++;
        }

        return false;
    }
}