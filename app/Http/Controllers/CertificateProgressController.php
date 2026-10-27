<?php

namespace App\Http\Controllers;

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\CertificateAttestation;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Services\CertificateLifecycleService;
use App\Support\Authorization\CertificateProgressAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateProgressController extends Controller
{
    public function __construct(private CertificateProgressAccess $access, private CertificateLifecycleService $lifecycle) {}

    public function show(CertificateEnrollment $certificateEnrollment): View
    {
        abort_unless($this->access->canView($this->actor(), $certificateEnrollment), 403);
        $certificateEnrollment->loadMissing(['candidate', 'certificate.programs', 'supervisingCoach', 'awards.endorsedBy', 'awards.awardedBy', 'awards.revokedBy']);
        $result = $this->lifecycle->evaluate($certificateEnrollment);
        $actor = $this->actor();
        $details = $this->access->canViewDetails($actor, $certificateEnrollment);
        $canManage = $this->access->canManage($actor, $certificateEnrollment);
        $coach = $certificateEnrollment->supervisingCoach;
        $summary = ['met' => $result['met'], 'total' => $result['total'], 'satisfied' => $result['satisfied']];
        if ($details) {
            foreach ($result['requirements'] as $row) {
                foreach ($row['evidence'] as $evidence) {
                    $evidence['submission']?->loadMissing(['scores.field', 'dimensionValues.dimension', 'dimensionValues.option', 'recordedBy']);
                }
            }
        }

        return view('certification.progress', [
            'enrollment' => $certificateEnrollment, 'summary' => $summary,
            'progress' => $details ? $result : null,
            'canConfirm' => $details && $this->access->canConfirm($actor, $certificateEnrollment),
            'lifecycle' => [
                'pending' => $certificateEnrollment->awards->first(fn ($award) => $award->cycle === $certificateEnrollment->cycle && $award->awarded_at === null),
                'awards' => $certificateEnrollment->awards->whereNotNull('awarded_at')->values(),
                'previousAwardDate' => $this->lifecycle->previousAwardDate($certificateEnrollment),
                'prerequisites' => $this->lifecycle->prerequisites($certificateEnrollment),
                'expiresAt' => $this->lifecycle->expiresAt($certificateEnrollment, now()),
                'coachCanEndorse' => $coach !== null && $this->access->canEndorse($coach, $certificateEnrollment),
                'canEndorse' => $this->access->canEndorse($actor, $certificateEnrollment),
                'canManage' => $canManage,
                'coaches' => $canManage ? $this->lifecycle->eligibleCoaches($actor, $certificateEnrollment) : collect(),
                'history' => $details ? $certificateEnrollment->actionLogs()->with('user')->get() : collect(),
            ],
        ]);
    }

    public function attest(Request $request, CertificateEnrollment $certificateEnrollment, CertificateRequirement $requirement): RedirectResponse
    {
        abort_unless($this->access->canConfirm($this->actor(), $certificateEnrollment), 403);
        $phase = $certificateEnrollment->phase === CertificateRequirementPhase::Renewal && $certificateEnrollment->certificate?->renewal_matches_initial
            ? CertificateRequirementPhase::Initial : $certificateEnrollment->phase;
        abort_unless($requirement->certificate_id === $certificateEnrollment->certificate_id
            && $requirement->phase === $phase && $requirement->kind === CertificateRequirementKind::Attestation, 404);
        $data = $request->validate(['satisfied' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:10000']]);
        DB::transaction(function () use ($certificateEnrollment, $requirement, $data): void {
            $certificateEnrollment->refreshForUpdate();
            CertificateAttestation::query()->updateOrCreate([
                'certificate_enrollment_id' => $certificateEnrollment->id,
                'certificate_requirement_id' => $requirement->id,
                'cycle' => $certificateEnrollment->cycle,
            ], [
                'satisfied' => (bool) $data['satisfied'], 'note' => $data['note'] ?? null,
                'satisfied_at' => now(), 'satisfied_by_user_id' => $this->actor()->id,
            ]);
        });

        return redirect()->route('certificate-enrollments.show', [
            'certificateEnrollment' => $certificateEnrollment,
            'agreement_id' => $request->integer('agreement_id') ?: null,
        ])->with('success', 'Coach confirmation saved.');
    }
}
