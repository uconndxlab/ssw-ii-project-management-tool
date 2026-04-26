<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can manage projects.');

        $query = Project::withCount('programs');

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'programs':
                $query->orderBy('programs_count', $direction);
                break;
            case 'active':
                $query->orderBy('active', $direction);
                break;
            default:
                $query->orderBy('name', $direction);
        }

        $projects = $query->paginate(20)->withQueryString();

        return view('projects.index', compact('projects', 'sort', 'direction'));
    }

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create projects.');
        return view('projects.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create projects.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        $project = Project::create($validated);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can view projects.');
        $project->load(['programs.activities']);

        $recentActivities = $project->programs
            ->flatMap(fn ($p) => $p->activities)
            ->sortByDesc('engagement_date')
            ->take(10);

        return view('projects.show', compact('project', 'recentActivities'));
    }

    public function edit(Project $project)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit projects.');
        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update projects.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        $project->update($validated);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete projects.');
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
