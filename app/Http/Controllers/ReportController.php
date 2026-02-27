<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Project;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function engagements(Request $request)
    {
        // Default dates
        $defaultStart = now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = now()->format('Y-m-d');

        // Validate inputs
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
        ]);

        $startDate = $validated['start_date'] ?? $defaultStart;
        $endDate = $validated['end_date'] ?? $defaultEnd;
        $projectId = $validated['project_id'] ?? null;
        $programId = $validated['program_id'] ?? null;

        // Verify project access if provided
        if ($projectId) {
            $this->verifyProjectAccess($projectId);
        }

        // Get visible projects for dropdown
        $visibleProjects = $this->getVisibleProjects();
        
        // Get active programs for dropdown
        $programs = Program::where('active', true)->orderBy('name')->get();

        // Build base query with visibility enforcement
        $query = Engagement::query()
            ->with(['project.organization', 'user'])
            ->whereBetween('engagement_date', [$startDate, $endDate]);

        // Visibility enforcement: non-admins only see their assigned projects
        if (!Auth::user()->isAdmin()) {
            $projectIds = Auth::user()->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        }

        // Project filter
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Program filter
        if ($programId) {
            $query->whereHas('programs', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }

        // Get engagements
        $engagements = $query->get();

        // Aggregate data by project
        $projectData = [];
        foreach ($engagements as $engagement) {
            $pid = $engagement->project_id;
            
            if (!isset($projectData[$pid])) {
                $projectData[$pid] = [
                    'project' => $engagement->project,
                    'event_hours' => 0,
                    'prep_hours' => 0,
                    'followup_hours' => 0,
                    'total_hours' => 0,
                    'participant_count' => 0,
                    'engagement_count' => 0,
                ];
            }

            $projectData[$pid]['event_hours'] += $engagement->event_hours;
            $projectData[$pid]['prep_hours'] += $engagement->prep_hours ?? 0;
            $projectData[$pid]['followup_hours'] += $engagement->followup_hours ?? 0;
            $projectData[$pid]['total_hours'] += ($engagement->event_hours + ($engagement->prep_hours ?? 0) + ($engagement->followup_hours ?? 0));
            $projectData[$pid]['participant_count'] += $engagement->participant_count ?? 0;
            $projectData[$pid]['engagement_count']++;
        }

        // Sort by project name
        usort($projectData, fn($a, $b) => strcmp($a['project']->name, $b['project']->name));

        return view('reports.engagements', compact(
            'projectData',
            'visibleProjects',
            'programs',
            'startDate',
            'endDate',
            'projectId',
            'programId'
        ));
    }

    private function getVisibleProjects()
    {
        if (Auth::user()->isAdmin()) {
            return Project::with('organization')->orderBy('name')->get();
        }

        return Auth::user()->projects()->with('organization')->orderBy('name')->get();
    }

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
