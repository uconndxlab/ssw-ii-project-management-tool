<?php

namespace App\Http\Controllers;

use App\Models\ContactFamilyLoggingField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactFamilyLoggingFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactFamilyLoggingField::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('help_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('field_type')) {
            $query->where('field_type', $request->field_type);
        }

        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');

        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order')->orderBy('name');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $loggingFields = $query->paginate(20)->withQueryString();

        return view('contact-family-logging-fields.index', compact('loggingFields'));
    }

    public function create()
    {
        $fieldTypes = ContactFamilyLoggingField::fieldTypes();

        return view('contact-family-logging-fields.create', compact('fieldTypes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateField($request, 'contact_family_logging_fields');
        $validated['is_active'] = $request->boolean('is_active', true);

        ContactFamilyLoggingField::create($validated);

        return redirect()->route('contact-family-logging-fields.index')
            ->with('success', 'Contact family logging field created successfully.');
    }

    public function show(ContactFamilyLoggingField $contactFamilyLoggingField)
    {
        $contactFamilyLoggingField->load(['contactFamilies' => function ($query) {
            $query->select('contact_families.id', 'contact_families.name')->orderBy('contact_families.name');
        }]);

        return view('contact-family-logging-fields.show', compact('contactFamilyLoggingField'));
    }

    public function edit(ContactFamilyLoggingField $contactFamilyLoggingField)
    {
        $fieldTypes = ContactFamilyLoggingField::fieldTypes();

        return view('contact-family-logging-fields.edit', compact('contactFamilyLoggingField', 'fieldTypes'));
    }

    public function update(Request $request, ContactFamilyLoggingField $contactFamilyLoggingField)
    {
        $validated = $this->validateField($request, 'contact_family_logging_fields', $contactFamilyLoggingField->id);
        $validated['is_active'] = $request->boolean('is_active');

        if ($validated['name'] !== $contactFamilyLoggingField->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $contactFamilyLoggingField->update($validated);

        return redirect()->route('contact-family-logging-fields.index')
            ->with('success', 'Contact family logging field updated successfully.');
    }

    public function destroy(ContactFamilyLoggingField $contactFamilyLoggingField)
    {
        $familyCount = $contactFamilyLoggingField->contactFamilies()->count();

        if ($familyCount > 0) {
            return back()->with('error', "Cannot delete this field. It is currently used by {$familyCount} contact family/families.");
        }

        $contactFamilyLoggingField->delete();

        return redirect()->route('contact-family-logging-fields.index')
            ->with('success', 'Contact family logging field deleted successfully.');
    }

    private function validateField(Request $request, string $table, ?int $ignoreId = null): array
    {
        $uniqueRule = 'required|string|max:255|unique:' . $table . ',name';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        $validated = $request->validate([
            'name' => $uniqueRule,
            'field_type' => 'required|in:number,decimal,text,textarea,checkbox,select,document',
            'help_text' => 'nullable|string|max:1000',
            'options_json' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validated['field_type'] === 'select' && !empty($validated['options_json'])) {
            $options = json_decode($validated['options_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'options_json' => 'Invalid JSON format.',
                ]);
            }
            $validated['options_json'] = $options;
        } else {
            $validated['options_json'] = null;
        }

        return $validated;
    }
}
