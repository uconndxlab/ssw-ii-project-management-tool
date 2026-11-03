<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Services\ActivityActionLogService;
use App\Services\CertificationEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Edits only an activity's certification evidence, for supervising coaches (and their supervisors)
 * who can review an activity without being allowed to edit the rest of it.
 */
class ActivityCertificationController extends Controller
{
    public function __construct(
        private CertificationEvidenceService $certificationEvidence,
        private ActivityActionLogService $activityActionLogService,
    ) {}

    public function edit(Activity $activity): View
    {
        $this->authorize('reviewCertification', $activity);

        $activity->load(['activityType', 'agreements', 'programs']);
        $certificationForm = $this->certificationEvidence->formData($this->actor(), $activity->agreements, $activity);

        return view('activities.certification', compact('activity', 'certificationForm'));
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('reviewCertification', $activity);

        DB::transaction(function () use ($request, $activity) {
            $activity->refreshForUpdate();
            $this->certificationEvidence->save($activity, $this->actor(), $request->only(['certification_candidates', 'needs_certification_review', 'review_coach_user_ids']));
            $this->activityActionLogService->record($activity, ActivityAction::Update);
        });

        return $this->redirectAfterSave($activity, 'Certification review saved.');
    }
}
