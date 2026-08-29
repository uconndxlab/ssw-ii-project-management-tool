<?php

namespace App\Http\Requests;

use App\Enums\AccessProfile;
use App\Enums\ProgramScopeMode;
use App\Models\User;
use App\Support\Authorization\ScopeSync;
use App\Support\Authorization\UserAccess;
use App\Support\ProjectProgramScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null) {
            return false;
        }

        $target = $this->route('user');
        if ($target instanceof User) {
            return $actor->can('update', $target);
        }

        return $actor->can('create', User::class);
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
            'access_profile' => ['required', Rule::in(AccessProfile::values())],
            'is_supervisor' => ['nullable', 'boolean'],
            'privilege_coverage' => ['nullable', Rule::in(['system', 'specific'])],
            'privilege_system_admin' => ['nullable', 'boolean'],
            'privilege_entries' => ['nullable', 'array'],
            'privilege_entries.*.scope_type' => ['nullable', 'in:project,program'],
            'privilege_entries.*.scope_id' => ['nullable', 'integer'],
            'privilege_entries.*.admin' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('active', true)->where('is_supervisor', true)),
            ],
            'program_scope_mode' => ['required', Rule::in(ProgramScopeMode::values())],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['distinct', 'exists:teams,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->route('user');
            $actor = Auth::user();
            $access = UserAccess::for($actor);

            if ($user instanceof User && Auth::id() === $user->id) {
                $validator->errors()->add('access_profile', 'You cannot change your own permissions.');
            }

            if (!$this->boolean('active')) {
                if ($user instanceof User && Auth::id() === $user->id) {
                    $validator->errors()->add('active', 'You cannot deactivate your own user account.');
                }

                if ($user instanceof User && $access->lastActiveSystemAdminWouldBeRemoved($user)) {
                    $validator->errors()->add('active', 'You cannot deactivate the last active system administrator.');
                }
            }

            if ($user instanceof User && $access->lastActiveSystemAdminWouldBeRemoved($user)) {
                $keepsSystemAdmin = $this->input('access_profile') === AccessProfile::AdminViewer->value
                    && $this->input('privilege_coverage') === 'system'
                    && $this->boolean('privilege_system_admin')
                    && $this->boolean('active');
                if (! $keepsSystemAdmin) {
                    $validator->errors()->add('access_profile', 'You cannot remove the last active system administrator.');
                }
            }

            $profile = AccessProfile::tryFrom((string) $this->input('access_profile'));

            if ($profile === AccessProfile::AdminViewer) {
                $coverage = $this->input('privilege_coverage', 'specific');
                if ($coverage === 'system' && ! $access->isSystemAdmin()) {
                    $validator->errors()->add('privilege_coverage', 'Only a system administrator can assign system-wide access.');
                }
                if ($coverage === 'specific' && collect($this->input('privilege_entries', []))->filter(fn ($row) => ! empty($row['scope_id']))->isEmpty()) {
                    $validator->errors()->add('privilege_entries', 'Add at least one project or program to the access ledger.');
                }
            }

            if ($user instanceof User) {
                $supervisorId = $this->input('supervisor_id');

                if ($supervisorId !== null && $supervisorId !== '') {
                    if ($this->wouldCreateCircularReference($user, (int) $supervisorId)) {
                        $validator->errors()->add('supervisor_id', 'This supervisor assignment would create a circular reporting structure.');
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

            $existingMode = $user instanceof User ? $user->program_scope_mode : ProgramScopeMode::None;
            $existingProgramIds = $user instanceof User
                ? $user->programs()->pluck('programs.id')->all()
                : [];

            ScopeSync::validateSubmittedMode(
                $validator,
                $actor,
                $existingMode,
                ProgramScopeMode::from($this->input('program_scope_mode', ProgramScopeMode::Specific->value)),
            );
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $actor,
                $programIds->all(),
                $existingProgramIds,
            );

            ProjectProgramScope::validateModeSelection(
                $validator,
                $this->input('program_scope_mode', ProgramScopeMode::Specific->value),
                User::class,
                $projectIds->all(),
                $programIds->all()
            );

            if (! $user instanceof User) {
                $teamIds = collect($this->input('team_ids', []))->filter()->all();
                $hasProgram = $programIds->isNotEmpty()
                    || $this->input('program_scope_mode') === ProgramScopeMode::All->value;
                if (! $hasProgram && $teamIds === []) {
                    $validator->errors()->add('program_ids', 'Assign at least one in-scope program or team.');
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
