<?php

namespace App\Http\Requests;

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