<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $sort      = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'email'   => $query->orderBy('email', $direction),
            'role'    => $query->orderBy('role', $direction),
            'created' => $query->orderBy('created_at', $direction),
            default   => $query->orderBy('name', $direction),
        };

        $users = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request')) {
            return view('admin.users.partials.table', compact('users', 'sort', 'direction'));
        }

        return view('admin.users.index', compact('users', 'sort', 'direction'));
    }

    public function create()
    {
        return view('admin.users.create', [
            'user' => new User(),
            'supervisors' => $this->supervisorOptions(),
        ]);
    }

    public function store(AdminUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'supervisor_id' => $validated['supervisor_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'supervisors' => $this->supervisorOptions($user),
        ]);
    }

    public function update(AdminUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'supervisor_id' => $validated['supervisor_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $user->update($attributes);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function show(User $user)
    {
        $user->load(['supervisor', 'agreements.organizations', 'agreements.states', 'teams']);

        $recentActivities = $user->activities()
            ->with(['activityType', 'agreements'])
            ->orderByDesc('engagement_date')
            ->take(10)
            ->get();

        return view('admin.users.show', compact('user', 'recentActivities'));
    }

    private function supervisorOptions(?User $user = null)
    {
        $query = User::query()->orderBy('name', 'asc');

        if ($user?->exists) {
            $query->whereKeyNot($user->id);
        }

        return $query->get();
    }
}
