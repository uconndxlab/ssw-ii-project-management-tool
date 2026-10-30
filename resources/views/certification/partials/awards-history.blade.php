@php
    $details = $progress !== null;
    $awards = $lifecycle['awards'];
    $agreementId = request()->integer('agreement_id') ?: null;
@endphp
@if($details || $awards->isNotEmpty())
    <x-section-card :title="$details ? 'Awards and history' : 'Awards'">
        @if($awards->isEmpty())
            <p class="small text-muted">No awards yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm small align-middle">
                    <thead><tr><th scope="col">Cycle</th><th scope="col">Stage</th><th scope="col">Awarded</th><th scope="col">Expires</th><th scope="col">Status</th>@if($details)<th scope="col"><span class="visually-hidden">Record</span></th>@endif</tr></thead>
                    <tbody>
                        @foreach($awards as $award)
                            <tr>
                                <td>{{ $award->cycle }}</td>
                                <td>{{ $award->phase->label() }}</td>
                                <td>{{ $award->awarded_at->format('M j, Y') }} · {{ $award->awardedBy?->name ?? 'Former staff member' }}</td>
                                <td>{{ $award->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                                <td>{{ match (true) {
                                    $award->revoked_at !== null => 'Revoked '.$award->revoked_at->format('M j, Y'),
                                    $award->isExpired() => 'Expired',
                                    $awards->contains(fn ($later) => $later->cycle > $award->cycle) => 'Superseded',
                                    default => 'Valid',
                                } }}</td>
                                @if($details)<td class="text-end"><a href="{{ route('certificate-enrollments.awards.show', ['certificateEnrollment' => $enrollment, 'award' => $award, 'agreement_id' => $agreementId]) }}">View</a></td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($details)
            <h3 class="h6 mt-2">Action history</h3>
            @forelse($lifecycle['history'] as $log)
                <div class="border rounded px-3 py-2 mb-2 small d-flex justify-content-between gap-3">
                    <div class="min-w-0">
                        <div class="fw-semibold">{{ $log->user?->name ?? ($log->user_id ? 'Former staff member' : 'System') }}</div>
                        <div class="text-muted">{{ $log->action->label() }} · cycle {{ $log->cycle }}@if($log->action === \App\Enums\CertificateAction::CoachChanged) · {{ $log->context['from'] ?? 'none' }} → {{ $log->context['to'] ?? 'none' }}@elseif($log->action === \App\Enums\CertificateAction::Awarded && ($log->context['expires_at'] ?? null)) · expires {{ \Illuminate\Support\Carbon::parse($log->context['expires_at'])->format('M j, Y') }}@endif</div>
                        @if($log->reason)<div class="mt-1 text-break">{{ in_array($log->action, [\App\Enums\CertificateAction::Endorsed, \App\Enums\CertificateAction::Awarded], true) ? 'Note' : 'Reason' }}: “{{ $log->reason }}”</div>@endif
                    </div>
                    <div class="text-muted text-nowrap">{{ $log->created_at?->format('M j, Y g:i A') }}</div>
                </div>
            @empty
                <p class="small text-muted mb-0">No lifecycle actions recorded yet.</p>
            @endforelse
        @endif
    </x-section-card>
@endif
