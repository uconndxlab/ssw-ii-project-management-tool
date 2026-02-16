<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EngagementController extends Controller
{
    public function index()
    {
        $query = Engagement::with(['project', 'user']);

        // Visibility enforcement: non-admins only see engagements for assigned projects
        if (!Auth::user()->isAdmin()) {
            $projectIds = Auth::user()->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        }

        $engagements = $query->orderBy('engagement_date', 'desc')->paginate(50);
        
        return view('engagements.index', compact('engagements'));
    }

    public function create()
    {
        // Get projects visible to current user
        $projects = $this->getVisibleProjects();
        
        return view('engagements.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'engagement_date' => ['required', 'date'],
            'engagement_type' => ['required', 'in:' . implode(',', Engagement::TYPES)],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Verify user has access to this project
        $this->verifyProjectAccess($validated['project_id']);

        Engagement::create([
            'project_id' => $validated['project_id'],
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'engagement_type' => $validated['engagement_type'],
            'hours' => $validated['hours'],
            'notes' => $validated['notes'],
        ]);

        return redirect()
            ->route('engagements.index')
            ->with('success', 'Engagement logged successfully.');
    }

    public function destroy(Engagement $engagement)
    {
        // Admin can delete any engagement
        // Staff/consultant can only delete their own
        if (!Auth::user()->isAdmin() && $engagement->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own engagements.');
        }

        $engagement->delete();

        return redirect()
            ->route('engagements.index')
            ->with('success', 'Engagement deleted successfully.');
    }

    /**
     * Get projects visible to current user based on role
     */
    private function getVisibleProjects()
    {
        if (Auth::user()->isAdmin()) {
            return Project::with('organization')->orderBy('name')->get();
        }

        return Auth::user()->projects()->with('organization')->orderBy('name')->get();
    }

    /**
     * Verify current user has access to given project
     */
    private function verifyProjectAccess(int $projectId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        $hasAccess = Auth::user()->projects()->where('projects.id', $projectId)->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to this project.');
        }
    }
}
