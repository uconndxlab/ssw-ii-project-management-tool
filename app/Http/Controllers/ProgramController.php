<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Project;
use App\Support\Authorization\ScopeSync;
use App\Support\Authorization\UserAccess;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Program::class);

        $query = Program::query()->visibleTo(Auth::user())->with('projects');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereIlike('name', "%{$search}%");
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
        $this->authorize('create', Program::class);

        $projects = $this->assignableProjects();

        return view('programs.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Program::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:programs'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
        ]);

        $projectIds = ScopeSync::mergeProjectIds(
            Auth::user(),
            [],
            ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []),
        );

        if ($projectIds === []) {
            return back()->withErrors(['project_ids' => 'Select at least one project you administer.'])->withInput();
        }

        $program = Program::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $request->boolean('active', true),
        ]);

        $program->projects()->sync($projectIds);

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program created successfully.');
    }

    public function show(Program $program)
    {
        $this->authorize('view', $program);
        $program->load([
            'projects',
            'activities.activityType.contactFamily',
            'activities.user',
            'activities.agreements',
            'organizations.states',
        ]);

        $recentActivities = $program->activities
            ->sortBy([
                ['engagement_date', 'desc'],
                [fn ($activity) => mb_strtolower($activity->activityType?->name ?? ''), 'asc'],
                ['id', 'desc'],
            ])
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
        $this->authorize('update', $program);

        $program->load('projects');

        $projects = $this->assignableProjects($program);

        return view('programs.edit', compact('program', 'projects'));
    }

    public function update(Request $request, Program $program)
    {
        $this->authorize('update', $program);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:programs,name,' . $program->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
        ]);

        $program->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        $program->loadMissing('projects');
        $projectIds = ScopeSync::mergeProjectIds(
            Auth::user(),
            $program->projects->pluck('id')->all(),
            ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []),
        );

        if ($projectIds === []) {
            return back()->withErrors(['project_ids' => 'The program must stay on at least one project.'])->withInput();
        }

        $program->projects()->sync($projectIds);

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program updated successfully.');
    }

    public function destroy(Program $program)
    {
        $this->authorize('delete', $program);
        $program->delete();

        return redirect()
            ->route('programs.index')
            ->with('success', 'Program deleted successfully.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Project>
     */
    private function assignableProjects(?Program $program = null)
    {
        $access = UserAccess::for(Auth::user());

        $query = Project::query()->orderBy('name');

        if ($access->isSystemAdmin()) {
            return $query
                ->when(
                    $program,
                    fn ($q) => $q->where(fn ($inner) => $inner->where('active', true)->orWhereHas('programs', fn ($rel) => $rel->whereKey($program->id))),
                    fn ($q) => $q->where('active', true),
                )
                ->get();
        }

        $ids = $access->adminProjectIds();
        if ($program) {
            $ids = array_values(array_unique(array_merge(
                $ids,
                $program->projects->pluck('id')->map(fn ($id) => (int) $id)->all(),
            )));
        }

        return $query->whereIn('id', $ids ?: [0])->get();
    }
}
