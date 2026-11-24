<?php

namespace App\Http\Controllers;

use App\Enums\CertificateAction;
use App\Exceptions\CertificateLifecycleConflict;
use App\Models\Activity;
use App\Models\CertificateActionLog;
use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use App\Services\CertificateLifecycleService;
use App\Support\Authorization\CertificateProgressAccess;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CertificateLifecycleController extends Controller
{
    public function __construct(private CertificateLifecycleService $lifecycle, private CertificateProgressAccess $access) {}

    public function editHistory(CertificateEnrollment $certificateEnrollment): View
    {
        abort_unless($this->access->canRecordHistory($this->actor(), $certificateEnrollment), 403);

        return view('certification.history', [
            'enrollment' => $certificateEnrollment,
            'awards' => $certificateEnrollment->awards()->reorder('cycle')->get(),
            'blocker' => $this->lifecycle->historyStateBlocker($certificateEnrollment),
            'prerequisites' => $this->lifecycle->prerequisites($certificateEnrollment),
        ]);
    }

    public function updateHistory(Request $request, CertificateEnrollment $certificateEnrollment): RedirectResponse
    {
        abort_unless($this->access->canRecordHistory($this->actor(), $certificateEnrollment), 403);
        $request->validate(['cycle' => ['required', 'integer']]);

        return $this->run($request, $certificateEnrollment, 'Award history saved',
            fn () => $this->lifecycle->saveHistory($this->actor(), $certificateEnrollment, $request->all()));
    }

    public function endorse(Request $request, CertificateEnrollment $certificateEnrollment): RedirectResponse
    {
        $data = $request->validate(['cycle' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:10000']]);

        return $this->run($request, $certificateEnrollment, 'Endorsed for award',
            fn () => $this->lifecycle->endorse($this->actor(), $certificateEnrollment, (int) $data['cycle'], $data['note'] ?? null));
    }

    public function withdraw(Request $request, CertificateEnrollment $certificateEnrollment): RedirectResponse
    {
        $data = $request->validate(['cycle' => ['required', 'integer'], 'award_id' => ['required', 'integer'], 'reason' => ['nullable', 'string', 'max:10000']]);

        return $this->run($request, $certificateEnrollment, 'Endorsement withdrawn',
            fn () => $this->lifecycle->withdraw($this->actor(), $certificateEnrollment, (int) $data['cycle'], (int) $data['award_id'], $data['reason'] ?? null));
    }

    public function award(Request $request, CertificateEnrollment $certificateEnrollment): RedirectResponse
    {
        $data = $request->validate(['cycle' => ['required', 'integer'], 'award_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:10000']]);

        return $this->run($request, $certificateEnrollment, 'Certificate awarded',
            fn () => $this->lifecycle->award($this->actor(), $certificateEnrollment, (int) $data['cycle'], (int) $data['award_id'], $data['note'] ?? null));
    }

    public function revoke(Request $request, CertificateEnrollment $certificateEnrollment, CertificateAward $award): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:10000']], ['reason.required' => 'Explain why this award is being revoked. The reason is kept in the action history.']);

        return $this->run($request, $certificateEnrollment, 'Award revoked',
            fn () => $this->lifecycle->revoke($this->actor(), $certificateEnrollment, $award, $data['reason']));
    }

    public function changeCoach(Request $request, CertificateEnrollment $certificateEnrollment): RedirectResponse
    {
        $data = $request->validate(['coach_id' => ['required', 'integer'], 'reason' => ['nullable', 'string', 'max:10000']], ['coach_id.required' => 'Choose a supervising coach.']);

        return $this->run($request, $certificateEnrollment, 'Supervising coach updated',
            fn () => $this->lifecycle->changeCoach($this->actor(), $certificateEnrollment, (int) $data['coach_id'], $data['reason'] ?? null));
    }

    public function show(CertificateEnrollment $certificateEnrollment, CertificateAward $award): View
    {
        abort_unless($award->awarded_at !== null && $this->access->canViewDetails($this->actor(), $certificateEnrollment), 404);
        $certificateEnrollment->loadMissing(['candidate', 'certificate']);
        $award->loadMissing(['endorsedBy', 'awardedBy', 'revokedBy']);

        return view('certification.award', [
            'enrollment' => $certificateEnrollment, 'award' => $award,
            'historyLogs' => $this->historyLogsFor($certificateEnrollment, $award),
            'activities' => Activity::query()->whereKey(Arr::flatten((array) data_get($award->requirements_snapshot, 'requirements.*.counted.*.activity_id', [])))->get()->keyBy('id'),
            'superseded' => $certificateEnrollment->awards()->issued()->where('cycle', '>', $award->cycle)->exists(),
            'canManage' => $this->access->canManage($this->actor(), $certificateEnrollment),
        ]);
    }

    /**
     * History saves that created or changed this award, oldest first.
     *
     * @return Collection<int, CertificateActionLog>
     */
    private function historyLogsFor(CertificateEnrollment $enrollment, CertificateAward $award): Collection
    {
        $created = false;

        return $enrollment->actionLogs()->reorder('id')->with('user')
            ->whereIn('action', [CertificateAction::HistoryRecorded, CertificateAction::HistoryEdited])->get()
            ->filter(function (CertificateActionLog $log) use ($award, &$created): bool {
                $rows = $log->context['rows'] ?? [];
                $inRows = is_array($rows) && in_array($award->cycle, array_column($rows, 'cycle'), true);
                $relevant = $inRows && (! $created || isset($log->context['changes'][$award->cycle]));
                $created = $created || $inRows;

                return $relevant;
            })->values();
    }

    private function run(Request $request, CertificateEnrollment $enrollment, string $success, Closure $action): RedirectResponse
    {
        $target = route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => $request->integer('agreement_id') ?: null]);
        try {
            $action();
        } catch (AuthorizationException) {
            abort(403);
        } catch (CertificateLifecycleConflict $conflict) {
            return redirect($target)->with('error', $conflict->getMessage());
        }

        return redirect($target)->with('success', $success);
    }
}
