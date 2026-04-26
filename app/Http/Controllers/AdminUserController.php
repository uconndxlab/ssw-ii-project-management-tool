<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);
        
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('admin.users.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:admin,staff,consultant'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
        ]);

        // Validate supervisor is not self (will be checked after user is created if needed)
        // No circular reference possible on creation since user doesn't exist yet

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'supervisor_id' => $validated['supervisor_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Check if setting a supervisor would create a circular reference.
     * Traverses up the supervisor chain to detect cycles.
     */
    private function wouldCreateCircularReference(User $user, ?int $supervisorId, int $maxDepth = 50): bool
    {
        if ($supervisorId === null) {
            return false;
        }

        // Can't supervise yourself
        if ($user->id === $supervisorId) {
            return true;
        }

        // Traverse up the supervisor chain
        $currentSupervisorId = $supervisorId;
        $depth = 0;

        while ($currentSupervisorId !== null && $depth < $maxDepth) {
            $supervisor = User::find($currentSupervisorId);
            
            if (!$supervisor) {
                return false; // Supervisor doesn't exist, let validation handle it
            }

            // If we encounter the original user, there's a cycle
            if ($supervisor->supervisor_id === $user->id) {
                return true;
            }

            $currentSupervisorId = $supervisor->supervisor_id;
            $depth++;
        }

        return false;
    }
}
