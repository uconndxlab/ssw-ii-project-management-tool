<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Project;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = Program::with('project');

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        // Status filter
        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'status':
                $query->orderBy('active', $direction)->orderBy('name', 'asc');
                break;

            case 'created':
                $query->orderBy('created_at', $direction);
                break;

            case 'name':
            default:
                $query->orderBy('name', $direction);
                break;
        }

        $programs = $query->paginate(20)->withQueryString();

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('programs.partials.table', compact('programs', 'sort', 'direction'));
        }

        return view('programs.index', compact('programs', 'sort', 'direction'));
    }

    public function create()
    {
        $projects = Project::where('active', true)->orderBy('name')->get();
        return view('programs.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:programs'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        Program::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
            'project_id' => $validated['project_id'],
        ]);

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program created successfully.');
    }

    public function show(Program $program)
    {
        $program->load([
            'project',
            'activities.activityType',
            'activities.user',
            'activities.agreements',
            'agreements.states',
            'agreements.users',
            'organizations.states',
        ]);

        $recentActivities = $program->activities->sortByDesc('engagement_date')->take(10);

        // Unique agreements directly linked to the program
        $agreements = $program->agreements->sortBy('name');

        // Unique users across all agreements
        $staffMap = [];
        foreach ($agreements as $agreement) {
            foreach ($agreement->users as $user) {
                if (!isset($staffMap[$user->id])) {
                    $staffMap[$user->id] = clone $user;
                    $staffMap[$user->id]->via_agreements = collect();
                }
                $staffMap[$user->id]->via_agreements->push($agreement->name);
            }
        }
        $users = collect($staffMap)->sortBy('name');

        // Unique states from organizations + agreements
        $states = $program->organizations
            ->flatMap(fn ($o) => $o->states)
            ->merge($agreements->flatMap(fn ($a) => $a->states))
            ->unique('id')
            ->sortBy('name');

        $organizations = $program->organizations->sortBy('name');

        return view('programs.show', compact('program', 'recentActivities', 'agreements', 'users', 'states', 'organizations'));
    }

    public function edit(Program $program)
    {
        $projects = Project::where('active', true)->orderBy('name')->get();
        $program->load('project');
        return view('programs.edit', compact('program', 'projects'));
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:programs,name,' . $program->id],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        $program->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? false,
            'project_id' => $validated['project_id'],
        ]);

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program updated successfully.');
    }

    public function destroy(Program $program)
    {
        $program->delete();

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program deleted successfully.');
    }
}