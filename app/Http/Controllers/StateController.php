<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function index(Request $request)
    {
        $query = State::query()->withCount(['organizations', 'agreements']);

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereIlike('name', "%{$search}%");
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'organizations':
                $query->orderBy('organizations_count', $direction)->orderBy('name', 'asc');
                break;

            case 'agreements':
                $query->orderBy('agreements_count', $direction)->orderBy('name', 'asc');
                break;

            case 'created':
                $query->orderBy('created_at', $direction);
                break;

            case 'name':
            default:
                $query->orderBy('name', $direction);
                break;
        }

        $states = $query->paginate(20)->withQueryString();

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('states.partials.table', compact('states', 'sort', 'direction'));
        }

        return view('states.index', compact('states', 'sort', 'direction'));
    }

    public function create()
    {
        return view('states.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:states'],
        ]);

        State::create($validated);

        return redirect()
            ->route('states.index')
            ->with('success', 'State created successfully.');
    }

    public function show(State $state)
    {
        $state->load(['organizations.agreements', 'agreements.organizations', 'agreements.users']);

        $state->setRelation(
            'agreements',
            $state->agreements->where('active', true)->values()
        );

        // Deduplicate staff across all agreements in this state, tagging agreement names
        $staffMembersMap = [];
        foreach ($state->agreements as $agreement) {
            foreach ($agreement->users as $user) {
                if (!isset($staffMembersMap[$user->id])) {
                    $staffMembersMap[$user->id] = clone $user;
                    $staffMembersMap[$user->id]->via_agreements = collect();
                }
                $staffMembersMap[$user->id]->via_agreements->push($agreement->name);
            }
        }
        $staffMembers = collect($staffMembersMap)->sortBy('name');

        $recentActivities = $state->activities()
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByRecentDisplay()
            ->limit(10)
            ->get();

        return view('states.show', compact('state', 'staffMembers', 'recentActivities'));
    }

    public function edit(State $state)
    {
        return view('states.edit', compact('state'));
    }

    public function update(Request $request, State $state)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:states,name,' . $state->id],
        ]);

        $state->update($validated);

        return redirect()
            ->route('states.index')
            ->with('success', 'State updated successfully.');
    }

    public function destroy(State $state)
    {
        $state->delete();

        return redirect()
            ->route('states.index')
            ->with('success', 'State deleted successfully.');
    }
}
