<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function activities(Request $request)
    {
        // Default dates
        $defaultStart = now()->startOfMonth()->format('Y-m-d');
        $defaultEnd = now()->format('Y-m-d');

        // Validate inputs
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'agreement_id' => ['nullable', 'exists:agreements,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'contact_family_id' => ['nullable', 'exists:contact_families,id'],
            'activity_type_id' => ['nullable', 'exists:activity_types,id'],
        ]);

        $startDate = $validated['start_date'] ?? $defaultStart;
        $endDate = $validated['end_date'] ?? $defaultEnd;
        $agreementId = $validated['agreement_id'] ?? null;
        $programId = $validated['program_id'] ?? null;
        $contactFamilyId = $validated['contact_family_id'] ?? null;
        $activityTypeId = $validated['activity_type_id'] ?? null;

        // Verify agreement access if provided
        if ($agreementId) {
            $this->verifyAgreementAccess($agreementId);
        }

        // Get visible agreements for dropdown
        $visibleAgreements = $this->getVisibleAgreements();
        
        // Get active programs for dropdown
        $programs = Program::where('active', true)->orderBy('name')->get();
        
        // Get active contact families and activity types for dropdowns
        $contactFamilies = \App\Models\ContactFamily::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activityTypes = \App\Models\ActivityType::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Build base query with visibility enforcement
        $query = Activity::query()
            ->with(['agreements.organizations', 'agreements.states', 'user', 'activityType.contactFamily'])
            ->whereBetween('activity_date', [$startDate, $endDate]);

        // Visibility enforcement: non-admins only see their assigned agreements
        if (!Auth::user()->isAdmin()) {
            $agreementIds = Auth::user()->accessibleAgreementsQuery()->pluck('agreements.id');
            $query->whereHas('agreements', function ($q) use ($agreementIds) {
                $q->whereIn('agreements.id', $agreementIds);
            });
        }

        // Agreement filter
        if ($agreementId) {
            $query->whereHas('agreements', function ($q) use ($agreementId) {
                $q->where('agreements.id', $agreementId);
            });
        }

        // Program filter
        if ($programId) {
            $query->whereHas('programs', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }

        // Contact Family filter
        if ($contactFamilyId) {
            $query->whereHas('activityType.contactFamily', function ($q) use ($contactFamilyId) {
                $q->where('id', $contactFamilyId);
            });
        }

        // Activity Type filter
        if ($activityTypeId) {
            $query->where('activity_type_id', $activityTypeId);
        }

        // Get activities
        $activities = $query->get();

        // Aggregate data by agreement
        $agreementData = [];
        foreach ($activities as $activity) {
            // Get first agreement for grouping (activities can have multiple)
            $firstAgreement = $activity->agreements->first();
            $aid = $firstAgreement?->id ?? 0;
            
            if (!isset($agreementData[$aid])) {
                $agreementData[$aid] = [
                    'agreement' => $firstAgreement,
                    'activity_count' => 0,
                ];
            }

            $agreementData[$aid]['activity_count']++;
        }

        // Sort by agreement name
        usort($agreementData, fn($a, $b) => strcmp($a['agreement']->name, $b['agreement']->name));

        // If HTMX request, return only the results partial
        if ($request->header('HX-Request')) {
            return view('reports.partials.results-table', compact(
                'agreementData',
                'startDate',
                'endDate',
                'agreementId',
                'programId',
                'contactFamilyId',
                'activityTypeId'
            ));
        }

        return view('reports.activities', compact(
            'agreementData',
            'visibleAgreements',
            'programs',
            'contactFamilies',
            'activityTypes',
            'startDate',
            'endDate',
            'agreementId',
            'programId',
            'contactFamilyId',
            'activityTypeId'
        ));
    }

    private function getVisibleAgreements()
    {
        if (Auth::user()->isAdmin()) {
            return Agreement::query()->active()->with('organizations')->orderBy('name')->get();
        }

        return Auth::user()->accessibleAgreementsQuery()
            ->where('agreements.active', true)
            ->with('organizations')
            ->orderBy('name')
            ->get();
    }

    private function verifyAgreementAccess(int $agreementId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        if (!Auth::user()->hasAccessToAgreement($agreementId)) {
            abort(403, 'You do not have access to this agreement.');
        }
    }
}
