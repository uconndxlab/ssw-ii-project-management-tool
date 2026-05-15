<?php

namespace App\Http\Controllers;

use App\Models\ContactFamily;
use App\Models\ContactFamilyLoggingField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $contactFamilies = $query->get();

        return view('admin.contact-families.index', compact('contactFamilies'));
    }

    public function create()
    {
        $contactFamilyLoggingFields = ContactFamilyLoggingField::active()->ordered()->get();

        return view('admin.contact-families.create', compact('contactFamilyLoggingFields'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:contact_family_logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:contact_family_logging_fields,id'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $contactFamily = ContactFamily::create([
            'name' => $validated['name'],
            'active' => $validated['active'],
            'sort_order' => $validated['sort_order'],
        ]);

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
        $contactFamilyLoggingFields = ContactFamilyLoggingField::active()->ordered()->get();
        $contactFamily->load('contactFamilyLoggingFields');

        return view('admin.contact-families.edit', compact('contactFamily', 'contactFamilyLoggingFields'));
    }

    public function update(Request $request, ContactFamily $contactFamily)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name,' . $contactFamily->id],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contact_family_logging_field_ids' => ['nullable', 'array'],
            'contact_family_logging_field_ids.*' => ['exists:contact_family_logging_fields,id'],
            'required_contact_family_logging_field_ids' => ['nullable', 'array'],
            'required_contact_family_logging_field_ids.*' => ['exists:contact_family_logging_fields,id'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $contactFamily->update($validated);

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

        $contactFamily->delete();

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Contact family deleted successfully.');
    }
}
