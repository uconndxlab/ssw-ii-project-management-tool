@php
    $pending = $lifecycle['pending'];
    $current = $lifecycle['awards']->first();
    $closed = $enrollment->status === \App\Enums\CertificateEnrollmentStatus::Revoked;
    $coach = $enrollment->supervisingCoach;
    $prerequisites = $lifecycle['prerequisites'];
    $agreementId = request()->integer('agreement_id') ?: null;
    $details = $progress !== null;
    $actorId = auth()->id();
    $coachSide = $pending && $canConfirm && array_intersect(auth()->user()->access()->activityReferenceUserIds(), array_filter([$pending->endorsed_by_user_id, $enrollment->supervising_coach_user_id])) !== [];
    $prerequisiteNames = collect($prerequisites['items'])->pluck('certificate.name')->join(', ', $prerequisites['mode'] === 'any' ? ' or ' : ' and ');
    $steps = [
        ['Requirements', ...($pending
            ? ['done', ($pending->requirements_snapshot['met'] ?? '?').' / '.($pending->requirements_snapshot['total'] ?? '?').' met at endorsement']
            : [$summary['satisfied'] ? 'done' : 'current', $summary['met'].' / '.$summary['total'].' met'])],
        ['Coach endorsement', ...match (true) {
            (bool) $pending => ['done', ($pending->endorsedBy?->name ?? 'Former staff member').' · '.$pending->endorsed_at->format('M j')],
            $coach === null => ['blocked', 'No supervising coach'],
            ! $lifecycle['coachCanEndorse'] => ['blocked', 'No eligible coach'],
            default => [$summary['satisfied'] ? 'current' : 'wait', $coach->name],
        }],
        ['Manager award', ...match (true) {
            $pending && ! $prerequisites['satisfied'] => ['blocked', 'Prerequisite needed'],
            (bool) $pending => ['current', $lifecycle['canManage'] && $pending->endorsed_by_user_id !== $actorId ? 'You can award' : 'Certification manager'],
            default => ['wait', 'Certification manager'],
        }],
    ];
    [$nextTone, $nextText] = match (true) {
        $closed => ['danger', 'No further endorsement or award is available for this enrollment. Evidence and history are preserved.'],
        $pending && ! $prerequisites['satisfied'] => ['warning', 'Award is unavailable until '.$enrollment->candidate->name.' holds a valid '.$prerequisiteNames.' award. The endorsement stays in place.'],
        (bool) $pending => ['primary', 'A certification manager awards next. '.($pending->endorsedBy?->name ?? 'A former staff member').' endorsed on '.$pending->endorsed_at->format('M j, Y').'.'],
        $coach === null => ['warning', 'No supervising coach is assigned, so no one can endorse. A certification manager can assign one.'],
        ! $lifecycle['coachCanEndorse'] => ['warning', $coach->name.' no longer has coach duty for this certificate’s programs or access to this candidate, so no one can endorse. A certification manager can assign a supervising coach.'],
        $summary['satisfied'] => ['primary', $coach->name.' (supervising coach) reviews the work and endorses it for award.'],
        default => ['primary', $summary['met'].' of '.$summary['total'].' requirements met. When all are met, '.$coach->name.' (supervising coach) can endorse.'],
    };
@endphp

<x-section-card title="Certification status">
    @if($current)
        @php
            $by = $current->awardedBy?->name ?? 'a former staff member';
            [$icon, $iconClass, $title, $line] = match (true) {
                $current->revoked_at !== null => ['bi-x-circle-fill', 'text-danger', 'Cycle '.$current->cycle.' award revoked '.$current->revoked_at->format('M j, Y'),
                    $details ? 'Revoked by '.($current->revokedBy?->name ?? 'a former staff member').' · Reason: “'.$current->revoke_reason.'”' : 'Awarded '.$current->awarded_at->format('M j, Y').' by '.$by],
                $current->isExpired() => ['bi-clock-history', 'text-secondary', 'Cycle '.$current->cycle.' credential expired '.$current->expires_at?->format('M j, Y'), 'Awarded '.$current->awarded_at->format('M j, Y').' by '.$by],
                default => ['bi-award-fill', 'text-success', 'Certified · cycle '.$current->cycle.($current->phase === \App\Enums\CertificateRequirementPhase::Renewal ? ' (renewal)' : ''),
                    'Awarded '.$current->awarded_at->format('M j, Y').' by '.$by.' · '.($current->expires_at ? 'valid until '.$current->expires_at->format('M j, Y') : 'does not expire')],
            };
        @endphp
        <div class="border rounded p-3 d-flex flex-wrap align-items-center gap-3 mb-3">
            <i class="bi {{ $icon }} {{ $iconClass }} fs-4" aria-hidden="true"></i>
            <div class="flex-grow-1 min-w-0"><div class="fw-semibold">{{ $title }}</div><div class="small text-muted text-break">{{ $line }}</div></div>
            @if($details)<a class="app-link small" href="{{ route('certificate-enrollments.awards.show', ['certificateEnrollment' => $enrollment, 'award' => $current, 'agreement_id' => $agreementId]) }}">View award</a>@endif
        </div>
    @endif

    @unless($closed)
        <ol class="list-unstyled row g-2 mb-3" aria-label="Cycle {{ $enrollment->cycle }} steps">
            @foreach($steps as $index => [$label, $state, $detail])
                <li class="col-md-4">
                    <div @class(['border rounded p-2 h-100', 'border-primary' => $state === 'current', 'border-warning' => $state === 'blocked', 'bg-light' => $state === 'wait'])>
                        <div class="d-flex align-items-center gap-2 fw-semibold small">
                            @if($state === 'done')<i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                            @elseif($state === 'blocked')<i class="bi bi-exclamation-triangle-fill text-warning-emphasis" aria-hidden="true"></i>
                            @else<span @class(['badge rounded-pill', 'text-bg-primary' => $state === 'current', 'text-bg-secondary' => $state === 'wait'])>{{ $index + 1 }}</span>@endif
                            {{ $label }}<span class="visually-hidden"> — {{ ['done' => 'complete', 'current' => 'current step', 'blocked' => 'blocked', 'wait' => 'not yet'][$state] }}</span>
                        </div>
                        <div class="small text-muted mt-1 text-break">{{ $detail }}</div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endunless

    <div class="alert alert-{{ $nextTone }} mb-3">
        <div class="small fw-semibold text-uppercase text-muted">Next step</div>
        <div>{{ $nextText }}@if(! $closed && $lifecycle['previousAwardDate']) Work after {{ $lifecycle['previousAwardDate']->format('M j, Y') }} counts toward cycle {{ $enrollment->cycle }}.@endif</div>
    </div>

    @if($details)
        @if($progress['cycle_blocked'])<p class="alert alert-warning">This cycle needs its previous award date before activity evidence can count.</p>@endif
        @if($pending && ! $progress['satisfied'])
            <p class="small text-muted">Live progress is now {{ $summary['met'] }} / {{ $summary['total'] }}. The award uses the requirements recorded at endorsement; a certification manager can return it to the coach.</p>
        @endif
        @if(! $closed && $prerequisites['items'] !== [])
            <ul class="list-unstyled small mb-3">
                @foreach($prerequisites['items'] as $item)
                    <li class="d-flex gap-2">
                        <i class="bi {{ $item['award'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}" aria-hidden="true"></i>
                        <span>Prerequisite {{ $item['certificate']->name }}: {{ $item['award'] ? 'valid'.($item['award']->expires_at ? ' until '.$item['award']->expires_at->format('M j, Y') : '') : 'no valid award' }}</span>
                    </li>
                @endforeach
                <li class="text-muted">{{ $prerequisites['mode'] === 'any' ? 'Any one is enough.' : 'All are needed.' }} Checked when the certificate is awarded.</li>
            </ul>
        @endif
        <p class="small text-muted mb-0">Progress is calculated from current evidence. Requirements met still needs the supervising coach’s endorsement and a certification manager’s award.</p>
    @else
        <p class="small text-muted mb-0">Detailed progress, evidence and award history are available to admins, certification coaches and managers in scope.</p>
    @endif

    @unless($closed)
        @if($lifecycle['canEndorse'] && ! $pending && $summary['satisfied'])
            <details class="app-details app-details--panel border rounded mt-3" @if(old('lifecycle_form') === 'endorse') open @endif>
                <summary class="px-3 py-2 text-primary-emphasis"><i class="bi bi-patch-check" aria-hidden="true"></i> Endorse for award…</summary>
                <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.endorse', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreementId]) }}">
                    @csrf
                    <input type="hidden" name="lifecycle_form" value="endorse"><input type="hidden" name="cycle" value="{{ $enrollment->cycle }}">
                    <p class="small mb-3">You’re endorsing {{ $enrollment->candidate->name }} for {{ $enrollment->certificate->name }}, {{ $enrollment->phase->label() }} · cycle {{ $enrollment->cycle }}. All {{ $summary['total'] }} requirements are met now, and they are recorded with your endorsement.</p>
                    <x-form-field label="Note" for="endorse-note"><textarea class="form-control" rows="2" name="note" id="endorse-note">{{ old('lifecycle_form') === 'endorse' ? old('note') : '' }}</textarea></x-form-field>
                    <div class="d-flex flex-wrap align-items-center gap-2"><button type="submit" class="btn btn-sm btn-primary">Endorse</button><span class="small text-muted">A certification manager awards next. You can withdraw until then.</span></div>
                </form>
            </details>
        @elseif($canConfirm && ! $lifecycle['canEndorse'] && ! $pending && $summary['satisfied'] && $coach)
            <p class="small text-muted mt-3 mb-0">Only the supervising coach, {{ $coach->name }}, endorses this enrollment.</p>
        @endif

        @if($coachSide)
            <details class="app-details app-details--panel border rounded mt-3" @if(old('lifecycle_form') === 'withdraw') open @endif>
                <summary class="px-3 py-2"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Withdraw endorsement…</summary>
                <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.withdraw', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreementId]) }}">
                    @csrf @method('DELETE')
                    <input type="hidden" name="lifecycle_form" value="withdraw"><input type="hidden" name="cycle" value="{{ $enrollment->cycle }}"><input type="hidden" name="award_id" value="{{ $pending->id }}">
                    <x-form-field label="Reason" for="withdraw-reason"><textarea class="form-control" rows="2" name="reason" id="withdraw-reason"></textarea></x-form-field>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Withdraw endorsement</button>
                </form>
            </details>
        @endif

        @if($pending && $lifecycle['canManage'])
            @if($pending->endorsed_by_user_id === $actorId)
                <p class="small text-muted mt-3 mb-0">You endorsed this cycle, so another certification manager awards it.</p>
            @elseif($prerequisites['satisfied'])
                <details class="app-details app-details--panel border rounded mt-3" open>
                    <summary class="px-3 py-2 text-primary-emphasis"><i class="bi bi-award" aria-hidden="true"></i> Award certificate…</summary>
                    <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.award', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreementId]) }}">
                        @csrf
                        <input type="hidden" name="cycle" value="{{ $enrollment->cycle }}"><input type="hidden" name="award_id" value="{{ $pending->id }}">
                        <ul class="list-unstyled small mb-3">
                            <li><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> {{ $pending->requirements_snapshot['met'] ?? '?' }} / {{ $pending->requirements_snapshot['total'] ?? '?' }} requirements met at endorsement</li>
                            <li><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> Endorsed by {{ $pending->endorsedBy?->name ?? 'a former staff member' }} on {{ $pending->endorsed_at->format('M j, Y') }}@if($pending->endorsement_note) — “{{ $pending->endorsement_note }}”@endif</li>
                            <li><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> {{ $prerequisites['items'] === [] ? 'No prerequisites' : 'Prerequisites held' }}</li>
                            <li class="mt-2">Valid {{ $lifecycle['expiresAt'] ? 'until '.$lifecycle['expiresAt']->format('M j, Y').'. Renewal cycle '.($enrollment->cycle + 1).' opens automatically.' : 'with no expiry.' }}</li>
                        </ul>
                        <x-form-field label="Note" for="award-note"><textarea class="form-control" rows="2" name="note" id="award-note"></textarea></x-form-field>
                        <button type="submit" class="btn btn-sm btn-primary">Award certificate</button>
                    </form>
                </details>
            @endif
            @unless($coachSide)
                @php $returnSubmitted = old('lifecycle_form') === 'return'; @endphp
                <details class="app-details app-details--panel border rounded mt-2" @if($returnSubmitted) open @endif>
                    <summary class="px-3 py-2"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Return to coach…</summary>
                    <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.withdraw', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreementId]) }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="lifecycle_form" value="return"><input type="hidden" name="cycle" value="{{ $enrollment->cycle }}"><input type="hidden" name="award_id" value="{{ $pending->id }}">
                        <p class="small mb-3">Withdraws the endorsement for cycle {{ $enrollment->cycle }}. Evidence and confirmations stay; the coach can endorse again.</p>
                        <x-form-field label="Reason" for="return-reason" :name="$returnSubmitted ? 'reason' : null" required>
                            <textarea class="form-control @if($returnSubmitted && $errors->has('reason')) is-invalid @endif" rows="2" name="reason" id="return-reason" required>{{ $returnSubmitted ? old('reason') : '' }}</textarea>
                        </x-form-field>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Return to coach</button>
                    </form>
                </details>
            @endunless
        @endif

        @if($lifecycle['canChangeCoach'])
            @php $coachSubmitted = old('lifecycle_form') === 'coach'; @endphp
            <details class="app-details app-details--panel border rounded mt-2" @if($coachSubmitted || ! $lifecycle['coachCanEndorse']) open @endif>
                <summary class="px-3 py-2"><i class="bi bi-person-gear" aria-hidden="true"></i> Change supervising coach…</summary>
                <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.coach', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreementId]) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="lifecycle_form" value="coach">
                    <x-form-field label="Supervising coach" for="coach-id" :name="$coachSubmitted ? 'coach_id' : null" help="Coaches with coach duty for this certificate’s programs who can see this candidate." required>
                        <select class="form-select" name="coach_id" id="coach-id" required>
                            <option value="">Choose a coach…</option>
                            @foreach($lifecycle['coaches'] as $option)<option value="{{ $option->id }}" @selected((string) old('coach_id', $enrollment->supervising_coach_user_id) === (string) $option->id)>{{ $option->name }}</option>@endforeach
                        </select>
                    </x-form-field>
                    <x-form-field label="Reason" for="coach-reason"><input class="form-control" name="reason" id="coach-reason" value="{{ $coachSubmitted ? old('reason') : '' }}"></x-form-field>
                    <button type="submit" class="btn btn-sm btn-primary">Change coach</button>
                </form>
            </details>
        @endif
        @if($lifecycle['canManage'])
            @if($current?->isLive())
                @include('certification.partials.revoke-form', ['award' => $current])
            @endif
        @elseif($details && ! $canConfirm)
            <p class="small text-muted mt-3 mb-0">Endorsing needs the supervising coach, and awarding needs certification manager duty for this certificate’s programs. Duties are granted on Edit User.</p>
        @endif
    @endunless
</x-section-card>
