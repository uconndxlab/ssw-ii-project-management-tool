<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can manage teams.');

        $query = Team::withCount('users');

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'members':
                $query->orderBy('users_count', $direction);
                break;
            case 'active':
                $query->orderBy('active', $direction);
                break;
            default:
                $query->orderBy('name', $direction);
        }

        $teams = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request')) {
            return view('teams.partials.table', compact('teams', 'sort', 'direction'));
        }

        return view('teams.index', compact('teams', 'sort', 'direction'));
    }

    public function create()
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create teams.');

        $users = User::orderBy('name')->get();

        return view('teams.create', compact('users'));
    }

    public function store(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create teams.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'active' => $validated['active'],
        ]);

        if (!empty($validated['user_ids'])) {
            $team->users()->sync($validated['user_ids']);
        }

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can view teams.');

        $team->load(['users', 'agreements']);

        return view('teams.show', compact('team'));
    }

    public function edit(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit teams.');

        $users = User::orderBy('name')->get();
        $team->load('users');

        return view('teams.edit', compact('team', 'users'));
    }

    public function update(Request $request, Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update teams.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $team->update([
            'name' => $validated['name'],
            'active' => $validated['active'],
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete teams.');

        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}
