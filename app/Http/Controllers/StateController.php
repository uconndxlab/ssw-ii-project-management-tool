<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function index()
    {
        $states = State::withCount(['organizations', 'projects'])->orderBy('name')->paginate(20);
        
        return view('states.index', compact('states'));
    }

    public function create()
    {
        return view('states.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:states'],
        ]);

        State::create($validated);

        return redirect()
            ->route('states.index')
            ->with('success', 'State created successfully.');
    }

    public function edit(State $state)
    {
        return view('states.edit', compact('state'));
    }

    public function update(Request $request, State $state)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:states,name,' . $state->id],
        ]);

        $state->update($validated);

        return redirect()
            ->route('states.index')
            ->with('success', 'State updated successfully.');
    }

    public function destroy(State $state)
    {
        $state->delete();

        return redirect()
            ->route('states.index')
            ->with('success', 'State deleted successfully.');
    }
}
