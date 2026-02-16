<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\State;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::with('state')->orderBy('name')->paginate(20);
        
        return view('organizations.index', compact('organizations'));
    }

    public function create()
    {
        $states = State::orderBy('name')->get();
        
        return view('organizations.create', compact('states'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'exists:states,id'],
        ]);

        Organization::create($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $states = State::orderBy('name')->get();
        
        return view('organizations.edit', compact('organization', 'states'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'exists:states,id'],
        ]);

        $organization->update($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}
