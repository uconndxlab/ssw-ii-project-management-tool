<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Project;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Query engagements with filters and visibility
        $query = Engagement::with(['project.organization', 'user'])
            ->whereBetween('engagement_date', [$startDate, $endDate]);

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

        // Visibility enforcement
        if (!Auth::user()->isAdmin()) {
            $projectIds = Auth::user()->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        }

        // Get engagements
        $engagements = $query->get();

        // Group by project and compute totals
        $projectData = [];
        foreach ($engagements as $engagement) {
            $pid = $engagement->project_id;
            
            if (!isset($projectData[$pid])) {
                $projectData[$pid] = [
                    'project' => $engagement->project,
                    'technical_assistance' => 0,
                    'coaching' => 0,
                    'training' => 0,
                    'total' => 0,
                    'count' => 0,
                ];
            }

            $projectData[$pid][$engagement->engagement_type] += $engagement->hours;
            $projectData[$pid]['total'] += $engagement->hours;
            $projectData[$pid]['count']++;
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
