<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificationCandidateRequest;
use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CertificationCandidateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CertificationCandidate::class);
        $actor = $this->actor();
        $query = CertificationCandidate::query()->visibleTo($actor)->with($this->relationships());
        if (! $request->boolean('include_inactive')) {
            $query->active();
        }
        if ($request->filled('search')) {
            $query->where(fn (Builder $search) => $search->whereIlike('name', '%'.$request->string('search').'%')->orWhereIlike('email', '%'.$request->string('search').'%'));
        }
        if ($request->filled('program_id') || $request->filled('project_id')) {
            $query->whereHas('rosterLinks.agreement', function (Builder $agreements) use ($request, $actor) {
                $agreements->visibleTo($actor);
                if ($request->filled('program_id')) {
                    $agreements->whereHas('programs', fn (Builder $programs) => $programs->whereKey($request->integer('program_id')));
                }
                if ($request->filled('project_id')) {
                    $agreements->whereHas('programs.projects', fn (Builder $projects) => $projects->whereKey($request->integer('project_id')));
                }
            });
        }
        if ($request->boolean('supervised')) {
            $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->whereIn('supervising_coach_user_id', $actor->access()->activityReferenceUserIds()));
        }
        if ($request->filled('certificate_id') || $request->filled('status') || $request->filled('coach_id')) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($request, $actor) {
                $this->visibleEnrollments($enrollments, $actor);
                if ($request->filled('certificate_id')) {
                    $enrollments->where('certificate_id', $request->integer('certificate_id'));
                }
                if ($request->filled('status')) {
                    $enrollments->lifecycleStatus((string) $request->string('status'));
                }
                if ($request->filled('coach_id')) {
                    $enrollments->where('supervising_coach_user_id', $request->integer('coach_id'));
                }
            });
        }
        $sort = in_array($request->input('sort'), ['name', 'start_date', 'active'], true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        $candidates = $query->orderBy($sort, $direction)->orderBy('id')->paginate(TablePagination::perPage($request))->withQueryString();
        $rows = $this->rowData($candidates->getCollection(), $actor);
        if ($request->header('HX-Request')) {
            return view('certification-candidates.partials.table', compact('candidates', 'rows', 'sort', 'direction'));
        }
        $filterCertificates = Certificate::query()->visibleTo($actor)->orderBy('name')->get();
        $filterPrograms = Program::query()->visibleTo($actor)->orderBy('name')->get();
        $filterProjects = Project::query()->visibleTo($actor)->orderBy('name')->get();
        $filterCoaches = User::query()->whereIn('id', CertificateEnrollment::query()->where(fn (Builder $enrollments) => $this->visibleEnrollments($enrollments, $actor))->select('supervising_coach_user_id'))->orderBy('name')->get();

        return view('certification-candidates.index', compact('candidates', 'rows', 'sort', 'direction', 'filterCertificates', 'filterPrograms', 'filterProjects', 'filterCoaches'));
    }

    public function show(CertificationCandidate $certificationCandidate): View
    {
        $this->authorize('view', $certificationCandidate);
        $candidate = $certificationCandidate->load($this->relationships());
        $row = $this->rowData(collect([$candidate]), $this->actor())[$candidate->id];
        $tagged = fn () => Activity::query()->whereHas('certificationCandidates', fn (Builder $tags) => $tags->where('certification_candidate_id', $candidate->id));
        $recentActivities = $tagged()->visibleTo($this->actor())
            ->with(['activityType.contactFamily', 'user', 'agreements'])->orderByRecentDisplay()->limit(10)->get();
        $hiddenActivityCount = $tagged()->count() - $tagged()->visibleTo($this->actor())->count();

        return view('certification-candidates.show', compact('candidate', 'row', 'recentActivities', 'hiddenActivityCount'));
    }

    public function edit(CertificationCandidate $certificationCandidate): View
    {
        $this->authorize('update', $certificationCandidate);

        return view('certification-candidates.edit', ['candidate' => $certificationCandidate]);
    }

    public function update(CertificationCandidateRequest $request, CertificationCandidate $certificationCandidate): RedirectResponse
    {
        $certificationCandidate->update($request->validated());

        return redirect()->route('certification-candidates.show', $certificationCandidate)->with('success', 'Candidate updated successfully.');
    }

    /** @return list<string> */
    private function relationships(): array
    {
        return ['enrollments.certificate.programs', 'enrollments.supervisingCoach', 'enrollments.awards', 'rosterLinks.agreement.programs'];
    }

    /**
     * Batched equivalent of CertificateProgressAccess::canView.
     *
     * @param  Builder<CertificateEnrollment>  $query
     */
    private function visibleEnrollments(Builder $query, User $actor): void
    {
        $query->whereIn('certificate_id', Certificate::query()->visibleTo($actor)->select('id'))
            ->whereHas('candidate.rosterLinks', fn (Builder $links) => $links->whereIn('agreement_id', Agreement::query()->visibleTo($actor)->select('id')));
    }

    /**
     * @param  Collection<int, CertificationCandidate>  $candidates
     * @return array<int, array{enrollments: Collection<int, CertificateEnrollment>, agreements: Collection<int, Agreement>, hiddenCertificates: int, hiddenAgreements: int}>
     */
    private function rowData(Collection $candidates, User $actor): array
    {
        $enrollmentIds = $candidates->flatMap(fn (CertificationCandidate $candidate) => $candidate->enrollments->modelKeys());
        $visibleEnrollmentIds = CertificateEnrollment::query()->whereKey($enrollmentIds)->where(fn (Builder $query) => $this->visibleEnrollments($query, $actor))->pluck('id')->flip();
        $unstartedIds = CertificateEnrollment::query()->whereKey($enrollmentIds)->unstarted()->pluck('id')->flip();
        $agreementIds = $candidates->flatMap(fn (CertificationCandidate $candidate) => $candidate->rosterLinks->pluck('agreement_id'));
        $visibleAgreementIds = Agreement::query()->visibleTo($actor)->whereKey($agreementIds)->pluck('id')->flip();
        $rows = [];
        foreach ($candidates as $candidate) {
            $enrollments = $candidate->enrollments->filter(fn (CertificateEnrollment $enrollment) => $visibleEnrollmentIds->has($enrollment->id));
            foreach ($enrollments as $enrollment) {
                $enrollment->primeUnstarted($unstartedIds->has($enrollment->id));
            }
            $agreements = $candidate->rosterLinks->pluck('agreement')->filter(fn (Agreement $agreement) => $visibleAgreementIds->has($agreement->id));
            $rows[$candidate->id] = ['enrollments' => $enrollments, 'agreements' => $agreements, 'hiddenCertificates' => $candidate->enrollments->count() - $enrollments->count(), 'hiddenAgreements' => $candidate->rosterLinks->count() - $agreements->count()];
        }

        return $rows;
    }
}
