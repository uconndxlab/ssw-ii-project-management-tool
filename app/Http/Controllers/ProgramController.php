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
            'active' => ['nullable', 'boolean'],
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        Program::create([
            'name' => $validated['name'],
            'active' => $validated['active'] ?? true,
            'project_id' => $validated['project_id'],
        ]);

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program created successfully.');
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
            'active' => ['nullable', 'boolean'],
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        $program->update([
            'name' => $validated['name'],
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