<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\ContactFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityTypeController extends Controller
{
    public function __construct()
    {
        // Ensure only admins can access
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function index()
    {
        $activityTypes = ActivityType::with('contactFamily')
            ->orderBy('contact_family_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.activity-types.index', compact('activityTypes'));
    }

    public function create()
    {
        $contactFamilies = ContactFamily::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.activity-types.create', compact('contactFamilies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Check uniqueness within contact family
        $exists = ActivityType::where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'An activity type with this name already exists in the selected contact family.']);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ActivityType::create($validated);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type created successfully.');
    }

    public function edit(ActivityType $activityType)
    {
        $contactFamilies = ContactFamily::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.activity-types.edit', compact('activityType', 'contactFamilies'));
    }

    public function update(Request $request, ActivityType $activityType)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Check uniqueness within contact family (excluding current record)
        $exists = ActivityType::where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $activityType->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'An activity type with this name already exists in the selected contact family.']);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $activityType->update($validated);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type updated successfully.');
    }

    public function destroy(ActivityType $activityType)
    {
        // Check if activity type is used in activities
        if ($activityType->activities()->count() > 0) {
            return redirect()
                ->route('activity-types.index')
                ->with('error', 'Cannot delete activity type that is used in activities.');
        }

        $activityType->delete();

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type deleted successfully.');
    }

    /**
     * HTMX endpoint: Get activity types for a specific contact family
     */
    public function getByFamily(Request $request)
    {
        $contactFamilyId = $request->input('contact_family_id');
        
        if (!$contactFamilyId) {
            return response('<option value="">Select activity type...</option>');
        }

        $activityTypes = ActivityType::where('contact_family_id', $contactFamilyId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $html = '<option value="">Select activity type...</option>';
        foreach ($activityTypes as $type) {
            $html .= '<option value="' . $type->id . '">' . e($type->name) . '</option>';
        }

        return response($html);
    }
}
