<?php

namespace App\Http\Controllers;

use App\Models\LoggingField;
use Illuminate\Http\Request;
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

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');

        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $loggingFields = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request')) {
            return view('logging-fields.partials.table', compact('loggingFields'));
        }

        return view('logging-fields.index', compact('loggingFields'));
    }

    /**
     * Show the form for creating a new logging field.
     */
    public function create()
    {
        $fieldTypes = LoggingField::fieldTypes();
        $availabilityOptions = LoggingField::availabilityOptions();

        return view('logging-fields.create', compact('fieldTypes', 'availabilityOptions'));
    }

    /**
     * Store a newly created logging field.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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
        ]);

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

        LoggingField::create($validated);

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
        return view('logging-fields.edit', compact('loggingField', 'fieldTypes', 'availabilityOptions'));
    }

    /**
     * Update the specified logging field.
     */
    public function update(Request $request, LoggingField $loggingField)
    {
        $validated = $request->validate([
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
        ]);

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
