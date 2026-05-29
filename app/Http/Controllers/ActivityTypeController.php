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
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function index(Request $request)
    {
        $contactFamilies = ContactFamily::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = ActivityType::with('contactFamily');

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('contactFamily', function ($familyQuery) use ($search) {
                        $familyQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('contact_family_id')) {
            $query->where('contact_family_id', $request->integer('contact_family_id'));
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'contact_family':
                $query->join('contact_families', 'activity_types.contact_family_id', '=', 'contact_families.id')
                    ->select('activity_types.*')
                    ->orderBy('contact_families.sort_order')
                    ->orderBy('contact_families.name', $direction)
                    ->orderBy('activity_types.sort_order')
                    ->orderBy('activity_types.name');
                break;

            case 'duration_days':
                $query->orderBy('duration_days', $direction)
                    ->orderBy('name');
                break;

            case 'duration_hours':
                $query->orderBy('duration_hours', $direction)
                    ->orderBy('name');
                break;

            case 'active':
                $query->orderBy('active', $direction)
                    ->orderBy('name');
                break;

            case 'name':
            default:
                $query->orderBy('name', $direction);
                break;
        }

        $activityTypes = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('admin.activity-types.partials.filters', compact('contactFamilies', 'sort', 'direction'));
        }

        if ($request->header('HX-Request') === 'true') {
            return view('admin.activity-types.partials.table', compact('activityTypes', 'sort', 'direction'));
        }

        return view('admin.activity-types.index', compact(
            'activityTypes',
            'contactFamilies',
            'sort',
            'direction'
        ));
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
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $exists = ActivityType::where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected contact family.'
                ]);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['duration_days'] = $validated['duration_days'] ?? 0;
        $validated['duration_hours'] = $validated['duration_hours'] ?? 0;

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
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $exists = ActivityType::where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $activityType->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected contact family.'
                ]);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['duration_days'] = $validated['duration_days'] ?? 0;
        $validated['duration_hours'] = $validated['duration_hours'] ?? 0;

        $activityType->update($validated);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type updated successfully.');
    }

    public function destroy(ActivityType $activityType)
    {
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

    public function getByFamily(Request $request)
    {
        $contactFamilyId = $request->input('contact_family_id');
        $selectedActivityTypeId = (int) $request->input('activity_type_id');

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
            $selected = $selectedActivityTypeId === (int) $type->id ? ' selected' : '';
            $html .= '<option value="' . $type->id . '"' . $selected . '>' . e($type->name) . '</option>';
        }

        return response($html);
    }
}