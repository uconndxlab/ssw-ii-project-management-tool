<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\Project;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContactFamilyController extends Controller
{
    public function __construct()
    {
        // Ensure only admins can access
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function index(Request $request)
    {
        $query = ContactFamily::query()
            ->withCount('activityTypes')
            ->with(['programs.projects']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('contact_families.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('contact_families.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyContactFamilyIndexSort($query, $sort, $direction);

        $contactFamilies = $query->get();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('admin.contact-families.partials.table', compact('contactFamilies', 'sort', 'direction'));
        }

        return view('admin.contact-families.index', compact(
            'contactFamilies',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    private function applyContactFamilyIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'name' => $query->orderBy('contact_families.name', $direction),
            'activity_types' => $query->orderBy('activity_types_count', $direction)->orderBy('contact_families.name'),
            'active' => $query->orderBy('contact_families.active', $direction)->orderBy('contact_families.name'),
            'projects' => $query->orderByRaw($this->minContactFamilyProjectNameSql()." {$dir}")->orderBy('contact_families.name'),
            'programs' => $query->orderByRaw($this->minContactFamilyProgramNameSql()." {$dir}")->orderBy('contact_families.name'),
            default => $query->orderBy('contact_families.sort_order', $direction)->orderBy('contact_families.name'),
        };
    }

    private function minContactFamilyProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN contact_family_program cfp ON cfp.program_id = pp.program_id AND cfp.contact_family_id = contact_families.id
        ), '')";
    }

    private function minContactFamilyProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN contact_family_program cfp ON cfp.program_id = p.id AND cfp.contact_family_id = contact_families.id
        ), '')";
    }

    public function create()
    {
        $contactFamilyLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_contact_families', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();

        return view('admin.contact-families.create', compact('contactFamilyLoggingFields', 'projects'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name'],
            'helper_text' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
            'track_additional_time' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, ContactFamily::class, $projectIds, $programIds);

            if (ProjectProgramScope::normalizeMode($mode, ContactFamily::class) !== ProgramScopeMode::Specific) {
                return;
            }

            ProjectProgramScope::validateScopedAssignments(
                $validator,
                $programIds,
                ProjectProgramScope::normalizeIds($request->input('contact_family_logging_field_ids', [])),
                LoggingField::class,
                'contact_family_logging_field_ids',
                'Selected logging fields must be global or match one of the selected programs.'
            );
        });

        $validated = $validator->validate();

        $validated['active'] = $request->has('active');
        $validated['track_additional_time'] = $request->has('track_additional_time');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, ContactFamily::class)->value;

        $contactFamily = ContactFamily::create([
            'name' => $validated['name'],
            'helper_text' => $validated['helper_text'] ?? null,
            'active' => $validated['active'],
            'track_additional_time' => $validated['track_additional_time'],
            'sort_order' => $validated['sort_order'],
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);

        $contactFamily->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            ContactFamily::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

        $syncData = [];
        foreach (($validated['contact_family_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_contact_family_logging_field_ids'] ?? []),
            ];
        }
        $contactFamily->contactFamilyLoggingFields()->sync($syncData);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Activity family created successfully.');
    }

    public function edit(ContactFamily $contactFamily)
    {
        $contactFamilyLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_contact_families', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $contactFamily->load(['contactFamilyLoggingFields', 'programs.projects']);

        return view('admin.contact-families.edit', compact('contactFamily', 'contactFamilyLoggingFields', 'projects'));
    }

    public function update(Request $request, ContactFamily $contactFamily)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name,'.$contactFamily->id],
            'helper_text' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
            'track_additional_time' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, ContactFamily::class, $projectIds, $programIds);

            if (ProjectProgramScope::normalizeMode($mode, ContactFamily::class) !== ProgramScopeMode::Specific) {
                return;
            }

            ProjectProgramScope::validateScopedAssignments(
                $validator,
                $programIds,
                ProjectProgramScope::normalizeIds($request->input('contact_family_logging_field_ids', [])),
                LoggingField::class,
                'contact_family_logging_field_ids',
                'Selected logging fields must be global or match one of the selected programs.'
            );
        });

        $validated = $validator->validate();

        $validated['active'] = $request->has('active');
        $validated['track_additional_time'] = $request->has('track_additional_time');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, ContactFamily::class)->value;

        $contactFamily->update([
            'name' => $validated['name'],
            'helper_text' => $validated['helper_text'] ?? null,
            'active' => $validated['active'],
            'track_additional_time' => $validated['track_additional_time'],
            'sort_order' => $validated['sort_order'],
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);
        $contactFamily->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            ContactFamily::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

        $syncData = [];
        foreach (($validated['contact_family_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_contact_family_logging_field_ids'] ?? []),
            ];
        }
        $contactFamily->contactFamilyLoggingFields()->sync($syncData);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Activity family updated successfully.');
    }

    public function destroy(ContactFamily $contactFamily)
    {
        if ($contactFamily->activityTypes()->count() > 0) {
            return redirect()
                ->route('contact-families.index')
                ->with('error', 'Cannot delete activity family with existing activity types.');
        }

        ContactFamily::destroy($contactFamily->id);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Activity family deleted successfully.');
    }
}
