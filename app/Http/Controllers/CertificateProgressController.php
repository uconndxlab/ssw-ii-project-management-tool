<?php

namespace App\Http\Controllers;

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\CertificateAttestation;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Services\CertificateProgressService;
use App\Support\Authorization\CertificateProgressAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateProgressController extends Controller
{
    public function __construct(private CertificateProgressAccess $access, private CertificateProgressService $progress) {}

    public function show(CertificateEnrollment $certificateEnrollment): View
    {
        abort_unless($this->access->canView($this->actor(), $certificateEnrollment), 403);
        $certificateEnrollment->loadMissing(['candidate', 'certificate.programs', 'supervisingCoach']);
        $result = $this->progress->evaluate($certificateEnrollment);
        $details = $this->access->canViewDetails($this->actor(), $certificateEnrollment);
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
            'canConfirm' => $details && $this->access->canConfirm($this->actor(), $certificateEnrollment),
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

        return redirect()->route('certificate-enrollments.show', $certificateEnrollment)->with('success', 'Coach confirmation saved.');
    }
}
