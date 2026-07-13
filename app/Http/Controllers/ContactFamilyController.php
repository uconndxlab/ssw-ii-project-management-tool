<?php

namespace App\Http\Controllers;

use App\Models\ContactFamily;
use App\Models\LoggingField;
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
        $query = ContactFamily::withCount('activityTypes')
            ->with(['projects', 'programs'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $contactFamilies = $query->get();

        return view('admin.contact-families.index', compact('contactFamilies'));
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
            'active' => ['boolean'],
            'track_additional_time' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $selectedProgramIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                $selectedProgramIds
            );

            ProjectProgramScope::validateScopedAssignments(
                $validator,
                $selectedProgramIds,
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

        $contactFamily = ContactFamily::create([
            'name' => $validated['name'],
            'active' => $validated['active'],
            'track_additional_time' => $validated['track_additional_time'],
            'sort_order' => $validated['sort_order'],
        ]);

        $contactFamily->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $contactFamily->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        $syncData = [];
        foreach (($validated['contact_family_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_contact_family_logging_field_ids'] ?? []),
            ];
        }
        $contactFamily->contactFamilyLoggingFields()->sync($syncData);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Contact family created successfully.');
    }

    public function edit(ContactFamily $contactFamily)
    {
        $contactFamilyLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_contact_families', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $contactFamily->load(['contactFamilyLoggingFields', 'projects', 'programs']);

        return view('admin.contact-families.edit', compact('contactFamily', 'contactFamilyLoggingFields', 'projects'));
    }

    public function update(Request $request, ContactFamily $contactFamily)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name,' . $contactFamily->id],
            'active' => ['boolean'],
            'track_additional_time' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:logging_fields,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $selectedProgramIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                $selectedProgramIds
            );

            ProjectProgramScope::validateScopedAssignments(
                $validator,
                $selectedProgramIds,
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

        $contactFamily->update([
            'name' => $validated['name'],
            'active' => $validated['active'],
            'track_additional_time' => $validated['track_additional_time'],
            'sort_order' => $validated['sort_order'],
        ]);
        $contactFamily->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $contactFamily->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        $syncData = [];
        foreach (($validated['contact_family_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_contact_family_logging_field_ids'] ?? []),
            ];
        }
        $contactFamily->contactFamilyLoggingFields()->sync($syncData);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Contact family updated successfully.');
    }

    public function destroy(ContactFamily $contactFamily)
    {
        // Check if contact family has activity types
        if ($contactFamily->activityTypes()->count() > 0) {
            return redirect()
                ->route('contact-families.index')
                ->with('error', 'Cannot delete contact family with existing activity types.');
        }

        ContactFamily::destroy($contactFamily->id);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Contact family deleted successfully.');
    }
}
