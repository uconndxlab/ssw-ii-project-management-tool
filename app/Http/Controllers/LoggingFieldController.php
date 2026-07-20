<?php

namespace App\Http\Controllers;

use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\Project;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoggingFieldController extends Controller
{
    /**
     * Display a listing of logging fields.
     */
    public function index(Request $request)
    {
        $query = LoggingField::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('help_text', 'like', "%{$search}%");
            });
        }

        // Active/inactive filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Field type filter
        if ($request->filled('field_type')) {
            $query->where('field_type', $request->field_type);
        }

        // Availability filter
        if ($request->filled('availability') && array_key_exists($request->availability, LoggingField::availabilityOptions())) {
            $query->where($request->availability, true);
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhereDoesntHave('projects');
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhereDoesntHave('programs');
            });
        }

        if ($request->filled('contact_family_id')) {
            $query->whereHas('contactFamilies', fn ($relation) => $relation->where('contact_families.id', $request->integer('contact_family_id')));
        }

        $sort = $request->input('sort', 'sort_order');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyLoggingFieldIndexSort($query, $sort, $direction);

        $loggingFields = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterContactFamilies = ContactFamily::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('logging-fields.partials.table', compact('loggingFields', 'sort', 'direction'));
        }

        return view('logging-fields.index', compact(
            'loggingFields',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
            'filterContactFamilies',
        ));
    }

    private function applyLoggingFieldIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'name' => $query->orderBy('logging_fields.name', $direction),
            'field_type' => $query->orderBy('logging_fields.field_type', $direction)->orderBy('logging_fields.name'),
            'availability' => $query->orderBy('logging_fields.available_in_agreements', $direction)
                ->orderBy('logging_fields.available_in_contact_families', $direction)
                ->orderBy('logging_fields.available_in_activities', $direction)
                ->orderBy('logging_fields.name'),
            'is_active' => $query->orderBy('logging_fields.is_active', $direction)->orderBy('logging_fields.name'),
            'sort_order' => $query->orderBy('logging_fields.sort_order', $direction)->orderBy('logging_fields.name'),
            'projects' => $query->orderByRaw($this->minLoggingFieldProjectNameSql()." {$dir}")->orderBy('logging_fields.name'),
            'programs' => $query->orderByRaw($this->minLoggingFieldProgramNameSql()." {$dir}")->orderBy('logging_fields.name'),
            default => $query->orderBy('logging_fields.sort_order', $direction)->orderBy('logging_fields.name'),
        };
    }

    private function minLoggingFieldProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN logging_field_project lfp ON lfp.project_id = p.id AND lfp.logging_field_id = logging_fields.id
        ), '')";
    }

    private function minLoggingFieldProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN logging_field_program lfp ON lfp.program_id = p.id AND lfp.logging_field_id = logging_fields.id
        ), '')";
    }

    /**
     * Show the form for creating a new logging field.
     */
    public function create()
    {
        $fieldTypes = LoggingField::fieldTypes();
        $availabilityOptions = LoggingField::availabilityOptions();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();

        return view('logging-fields.create', compact('fieldTypes', 'availabilityOptions', 'projects'));
    }

    /**
     * Store a newly created logging field.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:logging_fields,name',
            'field_type' => 'required|in:number,decimal,text,textarea,checkbox,select,document',
            'help_text' => 'nullable|string|max:1000',
            'options_json' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_full_width' => 'boolean',
            'available_in_agreements' => 'boolean',
            'available_in_contact_families' => 'boolean',
            'available_in_activities' => 'boolean',
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        // Parse options JSON for select fields
        if ($validated['field_type'] === 'select' && !empty($validated['options_json'])) {
            $options = json_decode($validated['options_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['options_json' => 'Invalid JSON format'])->withInput();
            }
            $validated['options_json'] = $options;
        } else {
            $validated['options_json'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_full_width'] = $request->boolean('is_full_width');
        $validated['available_in_agreements'] = $request->boolean('available_in_agreements');
        $validated['available_in_contact_families'] = $request->boolean('available_in_contact_families');
        $validated['available_in_activities'] = $request->boolean('available_in_activities');

        $loggingField = LoggingField::create($validated);
        $loggingField->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $loggingField->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        return redirect()->route('logging-fields.index')
            ->with('success', 'Logging field created successfully.');
    }

    /**
     * Display the specified logging field.
     */
    public function show(LoggingField $loggingField)
    {
        $loggingField->load(['agreements' => function ($query) {
            $query->select('agreements.id', 'agreements.name')->orderBy('agreements.name');
        }, 'contactFamilies' => function ($query) {
            $query->select('contact_families.id', 'contact_families.name')->orderBy('contact_families.name');
        }]);

        return view('logging-fields.show', compact('loggingField'));
    }

    /**
     * Show the form for editing the specified logging field.
     */
    public function edit(LoggingField $loggingField)
    {
        $fieldTypes = LoggingField::fieldTypes();
        $availabilityOptions = LoggingField::availabilityOptions();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $loggingField->load(['projects', 'programs']);

        return view('logging-fields.edit', compact('loggingField', 'fieldTypes', 'availabilityOptions', 'projects'));
    }

    /**
     * Update the specified logging field.
     */
    public function update(Request $request, LoggingField $loggingField)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:logging_fields,name,' . $loggingField->id,
            'field_type' => 'required|in:number,decimal,text,textarea,checkbox,select,document',
            'help_text' => 'nullable|string|max:1000',
            'options_json' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_full_width' => 'boolean',
            'available_in_agreements' => 'boolean',
            'available_in_contact_families' => 'boolean',
            'available_in_activities' => 'boolean',
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        // Parse options JSON for select fields
        if ($validated['field_type'] === 'select' && !empty($validated['options_json'])) {
            $options = json_decode($validated['options_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['options_json' => 'Invalid JSON format'])->withInput();
            }
            $validated['options_json'] = $options;
        } else {
            $validated['options_json'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_full_width'] = $request->boolean('is_full_width');
        $validated['available_in_agreements'] = $request->boolean('available_in_agreements');
        $validated['available_in_contact_families'] = $request->boolean('available_in_contact_families');
        $validated['available_in_activities'] = $request->boolean('available_in_activities');

        // Regenerate slug if name changed
        if ($validated['name'] !== $loggingField->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $loggingField->update($validated);
        $loggingField->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $loggingField->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        return redirect()->route('logging-fields.index')
            ->with('success', 'Logging field updated successfully.');
    }

    /**
     * Remove the specified logging field.
     */
    public function destroy(LoggingField $loggingField)
    {
        // Check if field is in use
        $agreementCount = $loggingField->agreements()->count();
        $contactFamilyCount = $loggingField->contactFamilies()->count();

        if ($agreementCount > 0 || $contactFamilyCount > 0) {
            return back()->with('error', "Cannot delete this field. It is currently used by {$agreementCount} agreement(s) and {$contactFamilyCount} contact family/families.");
        }

        LoggingField::destroy($loggingField->id);

        return redirect()->route('logging-fields.index')
            ->with('success', 'Logging field deleted successfully.');
    }
}
