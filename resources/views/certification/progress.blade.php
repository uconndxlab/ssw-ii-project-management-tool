@extends('layouts.app')
@section('title', $enrollment->candidate->name.' — '.$enrollment->certificate->name)
@section('content')
<x-form-shell>
    <x-page-header :title="$enrollment->candidate->name" :description="$enrollment->certificate->name" entity-type="Certificate enrollment">
        <x-slot:meta>
            <span>{{ ucfirst($enrollment->phase->value) }} · Cycle {{ $enrollment->cycle }}</span>
            @if($enrollment->supervisingCoach)<span>Coach: {{ $enrollment->supervisingCoach->name }}</span>@endif
        </x-slot:meta>
    </x-page-header>
    <x-form-errors />
    <x-section-card title="Progress">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <strong>{{ $summary['met'] }} / {{ $summary['total'] }} requirements met</strong>
            <span class="badge {{ $summary['satisfied'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $summary['satisfied'] ? 'Requirements met' : 'In progress' }}</span>
        </div>
        @if($progress)
            <p class="small text-muted mt-2 mb-0">Progress is calculated from current evidence. Requirements met still needs coach endorsement and a manager’s award.</p>
            @if($progress['cycle_blocked'])<p class="alert alert-warning mt-3 mb-0">This cycle needs its previous award date before activity evidence can count.</p>@endif
        @else
            <p class="small text-muted mt-2 mb-0">Detailed progress and evidence are available to admins, certification coaches, and managers in scope.</p>
        @endif
    </x-section-card>
    @if($progress)
        <x-section-card :title="ucfirst($enrollment->phase->value).' requirements'">
            @forelse($progress['requirements']->groupBy(fn ($row) => $row['requirement']->certificate_requirement_group_id ?? 'ungrouped') as $groupId => $members)
                @php $groupResult = $progress['groups']->first(fn ($row) => (string) $row['group']->id === (string) $groupId); @endphp
                @if($groupResult)
                    <div class="bg-light border rounded p-3 mb-3">
                        <h3 class="h6 mb-1">{{ $groupResult['group']->label ?? 'Requirement group' }} · {{ $groupResult['group']->satisfy_mode->label() }}</h3>
                        <p class="small mb-0">{{ $groupResult['met'] }} / {{ $groupResult['target'] }} needed · {{ $groupResult['satisfied'] ? 'Met' : 'Not met' }}</p>
                    </div>
                @endif
                @foreach($members as $row)
                    @php
                        $requirement = $row['requirement'];
                        $attestation = $row['attestation'];
                    @endphp
                    <section class="border-bottom pb-3 mb-3" aria-labelledby="requirement-{{ $requirement->id }}">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h3 class="h6 mb-0" id="requirement-{{ $requirement->id }}">{{ $requirement->label ?: ($requirement->certificationTool?->name ?? $requirement->activityType?->name ?? $requirement->kind->label()) }}</h3>
                            <span class="small text-muted">{{ $requirement->kind === \App\Enums\CertificateRequirementKind::Attestation ? 'Coach confirmation' : ($requirement->kind === \App\Enums\CertificateRequirementKind::ToolSubmission ? 'Tool submissions' : 'Activity work') }}</span>
                            <span class="badge ms-auto {{ $row['satisfied'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $row['satisfied'] ? 'Met' : 'Not met' }}</span>
                        </div>
                        @if($requirement->kind === \App\Enums\CertificateRequirementKind::Attestation)
                            @if($attestation)
                                <p class="small mb-2">{{ $attestation->satisfied ? 'Confirmed' : 'Confirmation removed' }} by {{ $attestation->satisfiedBy?->name ?? 'Former staff member' }}@if($attestation->satisfied_at) on {{ $attestation->satisfied_at->format('M j, Y') }}@endif.@if($attestation->note) <span class="text-muted">{{ $attestation->note }}</span>@endif</p>
                            @endif
                            @if($canConfirm)
                                <details @if($errors->any() && (string) old('requirement_id') === (string) $requirement->id) open @endif>
                                    <summary class="small text-primary">{{ $attestation?->satisfied ? 'Change confirmation' : 'Confirm requirement…' }}</summary>
                                    <form class="mt-3" method="POST" action="{{ route('certificate-enrollments.attest', ['certificateEnrollment' => $enrollment, 'requirement' => $requirement, 'agreement_id' => request()->integer('agreement_id') ?: null]) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="requirement_id" value="{{ $requirement->id }}">
                                        <x-form-field label="Note" :for="'confirmation-note-'.$requirement->id">
                                            <textarea class="form-control" rows="2" name="note" id="confirmation-note-{{ $requirement->id }}">{{ (string) old('requirement_id') === (string) $requirement->id ? old('note') : $attestation?->note }}</textarea>
                                        </x-form-field>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <button type="submit" name="satisfied" value="1" class="btn btn-sm btn-primary">{{ $attestation?->satisfied ? 'Save confirmation' : 'Confirm requirement met' }}</button>
                                            @if($attestation?->satisfied)<button type="submit" name="satisfied" value="0" class="btn btn-sm btn-outline-danger">Remove confirmation</button>@endif
                                            <span class="small text-muted">Saves this confirmation only, with your name and the time.</span>
                                        </div>
                                    </form>
                                </details>
                            @elseif(!$attestation?->satisfied)
                                <p class="small text-muted mb-0">A certification coach in this certificate’s programs records this confirmation.</p>
                            @endif
                        @else
                            <ul class="list-unstyled small mb-2">
                                <li class="d-flex justify-content-between gap-2"><span>{{ $requirement->kind === \App\Enums\CertificateRequirementKind::ToolSubmission ? ($requirement->requires_passing ? 'Total passing submissions' : 'Total submissions') : 'Completion units' }}</span><strong>{{ $row['count'] }} / {{ $row['target'] }}</strong></li>
                                @foreach($row['rules'] as $ruleRow)
                                    <li class="d-flex justify-content-between gap-2 mt-1">
                                        <span>{{ $ruleRow['rule']->dimension->name }} {{ $ruleRow['rule']->mode->value === 'coverage' ? 'coverage' : 'quota' }} · {{ $ruleRow['rule']->dimension->options->whereIn('id', $ruleRow['rule']->option_ids)->pluck('label')->join(', ') }}</span>
                                        <strong class="text-nowrap">{{ $ruleRow['count'] }} / {{ $ruleRow['target'] }} · {{ $ruleRow['satisfied'] ? 'Met' : 'Not met' }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                            @if($row['window_start'])<p class="small text-muted mb-2">Work on or after {{ $row['window_start']->format('M j, Y') }} is within the rolling window.</p>@endif
                            @if($requirement->threshold_note)<p class="small text-muted mb-2">Guidance: {{ $requirement->threshold_note }} Coach judgment decides passing.</p>@endif
                            <details>
                                <summary class="small text-primary">Evidence: {{ $row['evidence']->whereNull('reason')->count() }} counted · {{ $row['evidence']->whereNotNull('reason')->count() }} not counted</summary>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm small align-middle mb-2">
                                        <thead><tr><th>Date</th><th>Activity</th><th>Evidence</th><th>Outcome / credit</th></tr></thead>
                                        <tbody>
                                            @forelse($row['evidence'] as $evidence)
                                                @php $activity = $evidence['tag']->activity; $submission = $evidence['submission']; @endphp
                                                <tr>
                                                    @if(auth()->user()->can('view', $activity))
                                                        <td class="text-nowrap">{{ $activity->engagement_date->format('M j, Y') }}</td>
                                                        <td><a href="{{ route('activities.show', $activity) }}">{{ $activity->activityType?->name ?? 'Activity' }}</a>@can('update', $activity)<div><a href="{{ route('activities.edit', $activity) }}#activity-certification">Edit evidence</a></div>@endcan</td>
                                                        <td>
                                                            @if($submission)
                                                                @foreach($submission->dimensionValues as $value)<div>{{ $value->dimension->name }}: {{ $value->option->label }}</div>@endforeach
                                                                @foreach($submission->scores as $score)<div>{{ $score->field->name }}: {{ rtrim(rtrim($score->value, '0'), '.') }}{{ $score->field->unit->value === 'percent' ? '%' : '' }}</div>@endforeach
                                                                @if($submission->notes)<div class="text-muted">{{ $submission->notes }}</div>@endif
                                                            @else {{ $evidence['tag']->completion_units }} unit(s) @endif
                                                        </td>
                                                        <td>{{ $submission?->outcome->label() ?? 'Activity work' }}<div class="{{ $evidence['reason'] ? 'text-muted' : 'text-success' }}">{{ $evidence['reason'] ? 'Not counted: '.$evidence['reason'] : 'Counted' }}</div></td>
                                                    @else
                                                        <td>—</td><td>Activity you can’t open</td><td>Evidence details restricted</td><td>{{ $evidence['reason'] ? 'Not counted' : 'Counted' }}</td>
                                                    @endif
                                                </tr>
                                            @empty<tr><td colspan="4" class="text-muted">No matching evidence recorded.</td></tr>@endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if($row['rules']->isNotEmpty())<p class="small text-muted mb-0">Total, coverage, and quotas independently use the same eligible submissions. A submission may contribute to all three.</p>@endif
                            </details>
                        @endif
                        @if($requirement->notes)<p class="small text-muted mt-2 mb-0">{{ $requirement->notes }}</p>@endif
                    </section>
                @endforeach
            @empty<p class="text-muted mb-0">No requirements configured for this phase.</p>@endforelse
        </x-section-card>
    @endif
</x-form-shell>
@endsection
