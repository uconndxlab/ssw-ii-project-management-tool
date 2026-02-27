<?php

namespace App\Http\Controllers;

use App\Models\ContactFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactFamilyController extends Controller
{
    public function __construct()
    {
        // Ensure only admins can access
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function index()
    {
        $contactFamilies = ContactFamily::withCount('activityTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.contact-families.index', compact('contactFamilies'));
    }

    public function create()
    {
        return view('admin.contact-families.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ContactFamily::create($validated);

        return redirect()
            ->route('contact-families.index')
            ->with('success', 'Contact family created successfully.');
    }

    public function edit(ContactFamily $contactFamily)
    {
        return view('admin.contact-families.edit', compact('contactFamily'));
    }

    public function update(Request $request, ContactFamily $contactFamily)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:contact_families,name,' . $contactFamily->id],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $contactFamily->update($validated);

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
