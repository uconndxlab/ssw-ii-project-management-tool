<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Project;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = Program::with('projects');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

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
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
        ]);

        $program = Program::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        $program->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids']));

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program created successfully.');
    }

    public function show(Program $program)
    {
        $program->load([
            'projects',
            'activities.activityType',
            'activities.user',
            'activities.agreements',
            'organizations.states',
        ]);

        $recentActivities = $program->activities
            ->sortByDesc('engagement_date')
            ->take(10)
            ->values();

        $agreements = $program->agreementsForDisplay();

        $states = $program->organizations
            ->flatMap(fn ($o) => $o->states)
            ->merge($agreements->flatMap(fn ($a) => $a->states))
            ->unique('id')
            ->sortBy('name');

        $organizations = $program->organizations->sortBy('name');

        $activityCount = $program->activities()->count();

        return view('programs.show', compact(
            'program',
            'recentActivities',
            'agreements',
            'states',
            'organizations',
            'activityCount',
        ));
    }

    public function edit(Program $program)
    {
        $program->load('projects');

        $projects = Project::query()
            ->where('active', true)
            ->orWhereHas('programs', fn ($query) => $query->whereKey($program->id))
            ->orderBy('name')
            ->get();

        return view('programs.edit', compact('program', 'projects'));
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:programs,name,' . $program->id],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
        ]);

        $program->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? false,
        ]);

        $program->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids']));

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
